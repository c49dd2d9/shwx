<?php

namespace App\Http\Middleware;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Redis;
use App\Exceptions\ApiSignException;
use Closure;

class Sign
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        /**
         * Params { ts, nonce, platform, sign }
         */
        // 获取当前Url
        $currentUrl = $request->path(); 
        // 白名单Url，设置后相应的url不验签，但如果是有{param}变量的url，则设置无效
        $whiteUrl = [ 'api/alipay/notify', 'api/wechat/pay/notify', 'api/app/test', 'api/user/nickname/login', 'api/user/phone/verify/code/login', 'api/user/send/phone/verifycode' ]; 
        if (in_array($currentUrl, $whiteUrl)) {
            return $next($request);
        }
        // 当前登录用户的Token，没有的话就是空字符串
        $userToken = $request->header('Authorization') ? $request->header('Authorization') : '';
        // 当前时间戳，因为js的时间戳的单位是毫秒
        $timestamp = time();
        $getTimeStamp = isset($_GET['ts']) ? $_GET['ts'] : 0;
        $getNonce = isset($_GET['nonce']) ? $_GET['nonce'] : 0;
        $platform = isset($_GET['platform']) ? $_GET['platform'] : '';
        $sign = isset($_GET['sign']) ? $_GET['sign'] : '';
        $appId = config('appid.'.$platform);
        $nonceCheck = Redis::get('nonce:'.$getNonce);
        if ($nonceCheck) {
            // 如果已经存在这个 nonce
            throw new ApiSignException('[请求唯一性校验异常]', '传入序列号:'.$getNonce, ' 用户Token:'.$userToken);
        }
        if (!$appId) {
            // 如果APPID不存在
            throw new ApiSignException('[无法找到APPID]', '传入Platform:'.$platform, ' 用户Token:'.$userToken);
        }
        if ($platform == 'Web') {
            // 如果是Web平台，appid应该是md5(ts + appid)
            // 如果是Web平台且登录，appid应该是md5(token + appid)
            $appId =  $userToken != '' ? md5($userToken.$appId) : md5($getTimeStamp.$appId);
        }
        if ($timestamp - $getTimeStamp > 500) {
            // 如果时间超过 600 秒（即10min）
            throw new ApiSignException('[时间有效性校验异常]', '当前时间戳:'.$timestamp, ' 传入时间戳:'.$getTimeStamp);
        }
        // 加密appid+app_secret
        $MD5AppID = md5($appId); 
        // 加密第一步获取的md5后的appid+当前url+Token+传入的时间戳+平台+nonce
        $step_2 = md5($MD5AppID.$currentUrl.$userToken.$getTimeStamp.$platform.$getNonce); 
        // 拼接传入的字符串
        $queryString = 'appid='.$appId.'&nonce='.$getNonce.'&platform='.$platform.'&ts='.$getTimeStamp.'&token='.$userToken;
        // sha256加密
        $finalSign = hash('sha256', $MD5AppID.$step_2.md5($getNonce).$queryString);
        if ($finalSign != $sign) {
            // 如果最终获得的签名跟传入的签名不想等
            throw new ApiSignException('[签名校验异常]', '传入签名:'.$sign, ' 生成签名'.$finalSign);
        }
        // 将 nonce 存入 Redis 24小时
        Redis::setex('nonce:'.$getNonce, 24 * 3600, 1);
        return $next($request);
    }
}
