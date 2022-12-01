<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Enums\PayConfig;
use App\Enums\UserConfig;
use Illuminate\Support\Facades\Log;
use App\Exceptions\ApiPayException;
use QrCode;
use App\Jobs\AliPayJob;
use App\Jobs\WechatPayJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\Controller;

// 支付Controller
class PayController extends Controller
{
    /**
     * @router /get/pay/program
     * @params  {}
     * @response { }
     * @name 获取充值方案
     */
    public function getPayProgram()
    {
        $data = Cache::remember(PayConfig::PayCacheKey, 30 * 24 * 3600, function() {
            return DB::table('ds_pay_specialpaytype')->whereIn('id', PayConfig::PayList)->select('id', 'promotionname', 'payitem', 'paymoney', 'billmoney', 'gold', 'yuepiao', 'cuigengpiao')->get();
        });
        return successJson($data);
    }
    /**
     * @router /buy/gold/alipay
     * @params  { program_id, money, type }
     * @response { }
     * @name 支付宝充值
     */
    public function alipay(Request $request)
    {
       $validrules = [
        'program_id' => 'required|numeric',
        'money' => 'required|numeric',
        'type' => 'required'
       ];
       $validmessage = [
        'program_id.required' => '您必须选择一个充值金额',
        'program_id.numeric' => '充值金额非法',
        'money.required' => '您必须选择一个充值金额',
        'money.numeric' => '充值金额非法',
        'type.required' => '支付行为非法'
       ];
       validateParams($request->all(), $validrules, $validmessage);
       $payType = $request->input('type');
       checkPayPlatform($payType, $request->input('money'), '支付宝');
       $userInfo = $request->get('userInfo');
       $userPayCount = Redis::get('userpay:count:'.$userInfo['id']);
       if ($userPayCount && $userPayCount >= UserConfig::UserPayLimitCount) {
        // 如果订单生成数量超过 UserPayLimitCount ，且缓存没过期
        throw new ApiPayException('GENERATE_ORDER_COUNT_EXCEED_THE_LIMIT', '[用户输入]'.$request->input('money'), '支付宝');
       }
       $user = DB::table('ds_user_info')->where('id', $userInfo['id'])->first();
       if (!$user || $user->islock == 1) {
        // 如果没有这个用户 或者用户已被锁定
        throw new ApiPayException('USER_CANNOT_FOUND', '[用户输入]'.$request->input('money'), '支付宝');
       } 
       $programInfo = DB::table('ds_pay_specialpaytype')->where('id', $request->input('program_id'))->first();
       if (!$programInfo) {
        // 如果没有这个支付方案
        throw new ApiPayException('PAY_PROGRAM_NOT_FOUND', '[用户输入]'.$request->input('money'), '支付宝');
       }
       if ($programInfo->paymoney != $request->input('money')) {
        // 如果支付方案和表单输入的 money 值不一样
        throw new ApiPayException('PAY_PROGRAM_NOT_FOUND', '[系统]'.$programInfo->paymoney.'[用户输入]'.$request->input('money'), '支付宝');
       }
       // 生成订单Id
       $currentOrderId = generateOrderId($user->id, 2);
       // 订单信息
       $orderInfo = [
        'out_trade_no' => $currentOrderId,
        'total_amount' => $programInfo->paymoney,
        'subject' => config('app.name').'网上充值',
        'body' => '充值'.$programInfo->gold.UserConfig::GoldName,
        'http_method'  => 'GET',
        'timeout_express' => UserConfig::AliPayOrderEfficient,
       ];
       // 根据输入的 type 来判定返回对应的跳转链接或者支付字符串
       switch ($payType) {
        case 'pc':
            $order = app('alipay')->web($orderInfo)->getTargetUrl();
            break;
        case 'wap':
            $order = app('alipay')->wap($orderInfo)->getTargetUrl();
            break;
        default:
            $order = app('alipay')->app($orderInfo)->getContent();
            break;
       } 
    $data = [
        'info' => $order,
        'platform' => $payType,
    ];
    // 创建订单表
    DB::table('ds_pay_recharge')->insert([
        'userid' => $user->id,
        'bookid' => 0,
        'chapterid' => 0,
        'cztype' => 0,
        'paymoney' => $programInfo->paymoney,
        'paytype' => 1,
        'orderno' => $currentOrderId,
        'gold' => $programInfo->gold,
        'payon' => 0,
        'specialpaytype' => $programInfo->id,
        'tuijian' => $programInfo->cuigengpiao,
        'yuepiao' => $programInfo->yuepiao,
        'message_info' => '支付宝充值'.$programInfo->gold.UserConfig::GoldName.'，订单生成时间:'.now(),
    ]);
    // 增加一个缓存，用来判定用户支付次数
    Redis::incr('userpay:count:'.$userInfo['id']);
    // 增加过期时间300秒，每次成功创建订单都会重置过期时间
    Redis::expire('userpay:count:'.$userInfo['id'], 300);
    // 清空第一页的缓存
    Cache::forget('user:order:'.$user->id.'_1');
    return successJson($data);
    }
    /**
     * @router /buy/gold/wechatpay
     * @params  { program_id, money, type }
     * @response { }
     * @name 微信充值
     */
    public function wechatPay(Request $request)
    {
        $validrules = [
            'program_id' => 'required|numeric',
            'money' => 'required|numeric',
            'type' => 'required'
        ];
        $validmessage = [
            'program_id.required' => '您必须选择一个充值金额',
            'program_id.numeric' => '充值金额非法',
            'money.required' => '您必须选择一个充值金额',
            'money.numeric' => '充值金额非法',
            'type.required' => '支付行为非法'
        ];
       validateParams($request->all(), $validrules, $validmessage);
       $payType = $request->input('type');
       checkPayPlatform($payType, $request->input('money'), '微信支付');
       $userInfo = $request->get('userInfo');
       $userPayCount = Redis::get('userpay:count:'.$userInfo['id']);
       if ($userPayCount && $userPayCount >= UserConfig::UserPayLimitCount) {
        // 如果订单生成数量超过 UserPayLimitCount ，且缓存没过期
        throw new ApiPayException('GENERATE_ORDER_COUNT_EXCEED_THE_LIMIT', '[用户输入]'.$request->input('money'), '微信支付');
       }
       $user = DB::table('ds_user_info')->where('id', $userInfo['id'])->first();
       if (!$user || $user->islock == 1) {
        // 如果没有这个用户 或者用户已被锁定
        throw new ApiPayException('USER_CANNOT_FOUND', '[用户输入]'.$request->input('money'), '微信支付');
       } 
       $programInfo = DB::table('ds_pay_specialpaytype')->where('id', $request->input('program_id'))->first();
       if (!$programInfo) {
        // 如果没有这个支付方案
        throw new ApiPayException('PAY_PROGRAM_NOT_FOUND', '[用户输入]'.$request->input('money'), '微信支付');
       }
       if ($programInfo->paymoney != $request->input('money')) {
        // 如果支付方案和表单输入的 money 值不一样
        throw new ApiPayException('PAY_PROGRAM_NOT_FOUND', '[系统]'.$programInfo->paymoney.'[用户输入]'.$request->input('money'), '微信支付');
       }
       // 生成订单
       $currentOrderId = generateOrderId($user->id, 3);
       $orderInfo = [
        'out_trade_no' => $currentOrderId,
        'total_fee' => $programInfo->paymoney * 100,
        'body' => config('app.name').'网上充值-充值'.$programInfo->gold.UserConfig::GoldName,
       ];
       switch ($payType) {
        case 'pc':
            $order = app('wechat_pay')->scan($orderInfo);
            $orderReturn = 'data:image/svg+xml;base64,'.base64_encode(QrCode::generate($order->code_url));
            break;
        case 'wap':
            $orderReturn = app('wechat_pay')->wap($orderInfo)->getTargetUrl();
            break;
        default:
            $order = app('wechat_pay')->app($orderInfo)->getContent();
            $orderReturn = json_decode($order);
            break;
       }
       $data = [
        'info' => $orderReturn,
        'platform' => $payType,
       ];
       // 创建订单表
        DB::table('ds_pay_recharge')->insert([
            'userid' => $user->id,
            'bookid' => 0,
            'chapterid' => 0,
            'cztype' => 0,
            'paymoney' => $programInfo->paymoney,
            'paytype' => 1,
            'orderno' => $currentOrderId,
            'gold' => $programInfo->gold,
            'payon' => 0,
            'specialpaytype' => $programInfo->id,
            'tuijian' => $programInfo->cuigengpiao,
            'yuepiao' => $programInfo->yuepiao,
            'message_info' => '微信充值'.$programInfo->gold.UserConfig::GoldName.'，订单生成时间:'.now(),
        ]);
       // 增加一个缓存，用来判定用户支付次数
        Redis::incr('userpay:count:'.$userInfo['id']);
        // 增加过期时间300秒，每次成功创建订单都会重置过期时间
        Redis::expire('userpay:count:'.$userInfo['id'], 300);
        // 清空第一页的缓存
        Cache::forget('user:order:'.$user->id.'_1');
       return successJson($data);
    }
    public function wechatPayNotify()
    {
        try {
            $data = app('wechat_pay')->verify();
            $order = DB::table('ds_pay_recharge')->where('orderno', $data->out_trade_no)->first();
            if (!$order) {
                return 'fail';
            }
            if ($order->payon == 1) {
                return app('wechat_pay')->success();
            }
            $sendQueueData = [
                'orderid' => $order->id,
                'trade_no' => $data->transaction_id,
                'userid' => $order->userid,
                'yuepiao' => $order->yuepiao,
                'tuijian' => $order->tuijian,
                'gold' => $order->gold,
            ];
            WechatPayJob::dispatch($sendQueueData);
            return app('wechat_pay')->success();
        } catch (\Throwable $th) {
            throw new ApiPayException('ORDER_VERIFY_FAILED', 0, '微信支付');
        }
    }
    public function aliPayNotify()
    {
        try {
            $data = app('alipay')->verify();
            if (!in_array($data['trade_status'], [ 'TRADE_SUCCESS', 'TRADE_FINISHED'])) {
                return app('alipay')->success();
            }
            $order = DB::table('ds_pay_recharge')->where('orderno', $data['out_trade_no'])->first();
            if (!$order) {
                return 'fail';
            }
            if ($order->payon == 1) {
                return app('alipay')->success();
            }
            $sendQueueData = [
                'orderid' => $order->id,
                'trade_no' => $data['trade_no'],
                'userid' => $order->userid,
                'yuepiao' => $order->yuepiao,
                'tuijian' => $order->tuijian,
                'gold' => $order->gold,
            ];
            AliPayJob::dispatch($sendQueueData);
            return app('alipay')->success();
        }   catch (\Throwable $th) {
            throw new ApiPayException('ORDER_VERIFY_FAILED', 0, '支付宝');
        }
    }
    public function appleInAppPurchase()
    {
        
    }
}