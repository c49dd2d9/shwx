<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Enums\UserConfig;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\UserRegister;
use App\Jobs\UserLoginProcess;
use App\Jobs\SendPhoneCode;
use App\Mail\UserFindPassword;
use App\Mail\UserChangeEmail;
use App\Http\Services\GaodeMap;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

// 用户Controller
class UserController extends Controller
{
    /**
     * @router /user/login
     * @params { account, password }
     * @response { token }
     * @name 用户登录
     */
    public function login(Request $request)
    {
        $validrules = [
            'account' => 'required',
            'password' => 'required',
        ];
        $validmessages = [
            'account.required' => '您必须输入您的帐号',
            'password.required' => '您必须输入您的密码',
        ];
        validateParams($request->only('account', 'password'), $validrules, $validmessages);
        if (Redis::ttl('userlocked:'.$request->input('account')) > 1) {
            // 如果存在封禁状态缓存，则不查询数据库直接禁止登录
            return errorJson('USER_ACCOUNT_IS_LOCKED');
        }
        $account_field = 'nickname';
        if (is_numeric($request->input('account')) && strlen($request->input('account')) == 11) {
            $account_field = 'phone';
        }
        if (Str::contains($request->input('account'), ['@', '.'])) {
            $account_field = 'email';
        }
        $user = User::where($account_field, e($request->input('account')))->first();
        if (!$user) {
            return errorJson('USER_CANNOT_FOUND');
        }
        if ($user->islock == UserConfig::UserAccountLocked) {
            // 如果用户被封禁, 把封禁状态写入缓存避免多次重复请求数据库
            Redis::setex('userlocked:'.$request->input('account'), UserConfig::UserLockedRedisCacheEfficientsecond, 1);
            return errorJson('USER_ACCOUNT_IS_LOCKED');
        }
        $userSalt = $user->passsalt;
        $userPassword = generatePassword($request->input('password'), $userSalt, false);
        if ($userPassword['password'] == $user->password) {
            $userData = [
                'id' => $user->id,
                'nickname' => $user->nickname,
                'avatar' => $user->headimg,
                'isvip' => $user->isvip,
                'level' => $user->level
            ];
            $userToken = generateUserToken($userData);
            $data = [
                'userid' => $user->id,
                'userip' => $request->ip(),
                'platform' => $request->header('platform') ? $request->header('platform') : 'Web',
                'accountType' => $account_field,
                'isSuccess' => true
            ];
            UserLoginProcess::dispatch($data);
            return successJson($userToken['salt'].UserConfig::UserTokenDelimiter.$userToken['token']);
        } else {
            $data = [
                'userid' => $user->id,
                'userip' => $request->ip(),
                'platform' => $request->header('platform') ? $request->header('platform') : 'Web',
                'accountType' => $account_field,
                'isSuccess' => false
            ];
            UserLoginProcess::dispatch($data);
            return errorJson('PASSWORD_ERROR');
        }
    }
    /**
     * @router /user/nickname/login
     * @params { nickname, password }
     * @response { token }
     * @name 用户昵称登录
     */
    public function nicknameLogin(Request $request)
    {
        $validrules = [
            'nickname' => 'required',
            'password' => 'required'
        ];
        $validmessages = [
            'nickname.required' => '您必须输入昵称',
            'password.required' => '您必须输入密码'
        ];
        validateParams($request->only('nickname', 'password'), $validrules, $validmessages);
        if (Redis::ttl('userlocked:'.$request->input('nickname')) > 1) {
            // 如果存在封禁状态缓存，则不查询数据库直接禁止登录
            return errorJson('USER_ACCOUNT_IS_LOCKED');
        }
        $user = User::where('nickname', e($request->input('nickname')))->first();
        if (!$user) {
            return errorJson('USER_CANNOT_FOUND');
        }
        if ($user->islock == UserConfig::UserAccountLocked) {
            // 如果用户被封禁, 把封禁状态写入缓存避免多次重复请求数据库
            Redis::setex('userlocked:'.$request->input('nickname'), UserConfig::UserLockedRedisCacheEfficientsecond, 1);
            return errorJson('USER_ACCOUNT_IS_LOCKED');
        }
        $userSalt = $user->passsalt;
        $userPassword = generatePassword($request->input('password'), $userSalt, false);
        if ($user->password == $userPassword['password']) {
            $userData = [
                'id' => $user->id,
                'nickname' => $user->nickname,
                'avatar' => $user->headimg,
                'isvip' => $user->isvip,
                'level' => $user->level
            ];
            $userToken = generateUserToken($userData);
            $data = [
                'userid' => $user->id,
                'userip' => $request->ip(),
                'platform' => $request->header('platform') ? $request->header('platform') : 'Web',
                'accountType' => 'nickname',
                'isSuccess' => true
            ];
            UserLoginProcess::dispatch($data);
            return successJson($userToken['salt'].UserConfig::UserTokenDelimiter.$userToken['token']);
        } else {
            $data = [
                'userid' => $user->id,
                'userip' => $request->ip(),
                'platform' => $request->header('platform') ? $request->header('platform') : 'Web',
                'accountType' => 'nickname',
                'isSuccess' => false
            ];
            UserLoginProcess::dispatch($data);
            return errorJson('PASSWORD_ERROR');
        }
    }
    /**
     * @router /user/phone/verify/code/login
     * @params { phone, code }
     * @response { token }
     * @name 用户手机验证码登录
     */
    public function phoneVerifyCodeLogin(Request $request)
    {
        $validrules = [
            'phone' => 'required',
            'code' => 'required',
        ];
        $validmessages = [
            'phone.required' => '您必须提供登录的手机号',
            'code.required' => '验证码必须输入'
        ];
        validateParams($request->only('phone', 'code'), $validrules, $validmessages);
        $codeInfo = Redis::get('phone:login:'.$request->input('phone'));
        if (!$codeInfo) {
            return errorJson('VERIFYCODE_NOT_FOUND');
        }
        if ($request->input('code') != $codeInfo) {
            return errorJson('VERIFYCODE_FAILED');
        }
        if (Redis::ttl('userlocked:'.$request->input('phone')) > 1) {
            // 如果存在封禁状态缓存，则不查询数据库直接禁止登录
            return errorJson('USER_ACCOUNT_IS_LOCKED');
        }
        $user = User::where('phone', $request->input('phone'))->first();
        if (!$user) {
            return errorJson('USER_CANNOT_FOUND');
        }
        if ($user->islock == UserConfig::UserAccountLocked) {
            // 如果用户被封禁, 把封禁状态写入缓存避免多次重复请求数据库
            Redis::setex('userlocked:'.$request->input('phone'), UserConfig::UserLockedRedisCacheEfficientsecond, 1);
            return errorJson('USER_ACCOUNT_IS_LOCKED');
        }
        $userData = [
            'id' => $user->id,
            'nickname' => $user->nickname,
            'avatar' => $user->headimg,
            'isvip' => $user->isvip,
            'level' => $user->level
        ];
        $userToken = generateUserToken($userData);
        $data = [
            'userid' => $user->id,
            'userip' => $request->ip(),
            'platform' => $request->header('platform') ? $request->header('platform') : 'Web',
            'accountType' => 'phone_verify_code',
            'isSuccess' => true
        ];
        UserLoginProcess::dispatch($data);
        return successJson($userToken['salt'].UserConfig::UserTokenDelimiter.$userToken['token']);
        
    }
    /**
     * @router /user/register
     * @params { account_type, account, password, confirm_password }
     * @response BaseResponse
     * @name 用户注册
     */
    public function register(Request $request)
    {
        $accountType = $request->input('account_type') ? $request->input('account_type') : 'phone';
        $validrules = [];
        switch ($accountType) {
            case 'phone':
                $validrules = [
                    'account_type' =>  'required',
                    'nickname'  => 'required|unique:ds_user_info|max:15',
                    'account' => 'required|unique:ds_user_info,phone|regex:/(1)[0-9]{10}/',
                    'password' => 'required',
                    'verify_code' => 'required',
                ];
                break;
            default:
                $validrules = [
                    'account_type' =>  'required',
                    'nickname'  => 'required|unique:ds_user_info|max:15',
                    'account' => 'required|email|unique:ds_user_info,email',
                    'password' => 'required',
                    'verify_code' => 'required',
                ];
                break;
        }
        $validmessages = [
            'account_type.required' => '请选择使用邮箱或手机号进行注册',
            'account.required' => '您必须输入您的帐号',
            'account.email' => '邮箱格式验证失败',
            'nickname.required' => '请输入您的昵称~',
            'nickname.unique' => '此昵称已有人使用',
            'account.regex' => '手机号格式验证失败，仅支持中国手机号，前面无需+86',
            'account.unique' => $accountType == 'phone' ? '此手机号已被注册' : '此邮箱已被注册',
            'password.required' => '您必须输入您的密码',
            'verify_code.required' => '您必须输入验证码,如果没有请先获取',
        ];
        validateParams($request->only('account_type', 'account', 'password'), $validrules, $validmessages);
        // 确认密码与密码不一致
        if ($request->input('confirm_password') != $request->input('password')) {
            return errorJson('PASSWORD_NOT_SAME_CONFIRMPASSWORD');
        }
        $verifyCode = Redis::get($account_field.':'.$request->input('account'));
        // 验证码没有生成或者过期了
        if (!$verifyCode) {
            return errorJson('VERIFYCODE_NOT_FOUND');
        }
        // 验证码输入不正确
        if ($request->input('verify_code') != $verifyCode) {
            return errorJson('VERIFYCODE_FAILED');
        }
        $userPassword = generatePassword($request->input('password'), '', true);
        $createUser = new User;
        $createUser->phone = $account_field == 'phone' ? e($request->input('account')) : null;
        $createUser->email = $account_field == 'email' ? e($request->input('account')) : null;
        $createUser->password = $userPassword['password'];
        $createUser->passsalt = $userPassword['salt'];
        $createUser->nickname = e($request->input('nickname'));
        $createUser->headimg = 'http://asset.shanhaiworld.com/headimg/defaulthead.png@!ohead.png';
        $createUser->sex = 2;
        $createUser->intro = '';
        $createUser->gold = 0;
        $createUser->registertime = date('Y-m-d H:i:s', time());
        $createUser->lastlogintime = date('Y-m-d H:i:s', time());
        $createUser->lastloginip = $request->ip();
        $createUser->level = 2;
        $createUser->experience = 80;
        $createUser->save();
        $userData = [
            'id' => $createUser->id,
            'nickname' => e($request->input('nickname')),
            'avatar' => 'http://asset.shanhaiworld.com/headimg/defaulthead.png@!ohead.png',
            'isvip' => 0,
            'level' => 2,
        ];
        $userToken = generateUserToken($userData);
        return successJson($userToken['salt'].UserConfig::UserTokenDelimiter.$userToken['token']);
        
    }
    /**
     * @router /user/send/phone/verifycode
     * @params { account, type }
     * @response BaseResponse
     * @name 发送手机验证码
     */
    public function sendPhoneVerifyCode(Request $request)
    {
        $validrules = [
            'account' => 'required|regex:/(1)[0-9]{10}/',
        ];
        validateParams($request->only('account'), $validrules);
        $sendType = $request->input('type') ? $request->input('type') : 1;
        $redisPrefix = '';
        switch ($sendType) {
            case 1:
                $redisPrefix = 'phone';
                $smsTemplate = 'SMS_180210314';
                break;
            case 2:
                $redisPrefix = 'changephone';
                $smsTemplate = 'SMS_180210312';
                break;
            case 4:
                $redisPrefix = 'phone:login';
                $smsTemplate = 'SMS_180210316';
                break;
            default:
                $redisPrefix = 'changepassword:phone';
                $smsTemplate = 'SMS_180210313';
                break;
        }
        $data = [
            'account' => $request->input('account'),
            'type' => $redisPrefix
        ];
        $getCodeTTL = Redis::ttl($redisPrefix.':'.$data['account']);
        if ($getCodeTTL > UserConfig::AllowResendTimeLeftsecond) {
            return errorJson('VERFIYCODE_SEND_FREQUENTLY');
        }
        $code = generateVerifyCode($data);
        SendPhoneCode::dispatch([
            'account' => $data['account'],
            'sms_template' => $smsTemplate,
            'code' => $code,
        ]);
        return successJson($code);
    }
    /**
     * @router /user/send/email/verifycode
     * @params { account }
     * @response BaseResponse
     * @name 发送邮箱验证码
     */
    public function sendEmailVerifyCode(Request $request)
    {
        $sendType = $request->input('type') ? $request->input('type') : 1;
        $redisPrefix = '';
        switch ($sendType) {
            case 1:
                $redisPrefix = 'email';
                break;
            case 2: 
                $redisPrefix = 'changeemail';
                break;
            default:
                $redisPrefix = 'changepassword:email';
                break;
        }
        $data = [
            'account' => e($request->input('account')),
            'type' => $redisPrefix,
        ];
        
        $getCodeTTL = Redis::TTL($redisPrefix.':'.$data['account']);
        if ($getCodeTTL > UserConfig::AllowResendTimeLeftsecond) {
            return errorJson('VERFIYCODE_SEND_FREQUENTLY');
        }
        $code = generateVerifyCode($data);
        $emailBladeData = [
            'code' => $code,
            'email' => $data['account'],
            'type' => $redisPrefix,
        ];
       if ($emailBladeData['type'] == 'email') {
        Mail::to($emailBladeData['email'])->queue(new UserRegister($emailBladeData));
       } elseif ($emailBladeData['type'] == 'changepassword:email') {
        Mail::to($emailBladeData['email'])->queue(new UserFindPassword($emailBladeData));
       } else {
        Mail::to($emailBladeData['email'])->queue(new UserChangeEmail($emailBladeData));
       }
        return successJson();
    }
    /**
     * @router /user/change/password
     * @params { account_type, account, password, code }
     * @response BaseResponse
     * @name 修改密码
     */
    public function changePassword(Request $request)
    {
        switch ($request->input('account_type')) {
            case 'phone':
                $validrules = [
                    'account_type' =>  'required',
                    'account' => 'required|regex:/(1)[0-9]{10}/',
                    'password' => 'required|min:6',
                    'code' => 'required',
                ];
                break;
            default:
                $validrules = [
                    'account_type' =>  'required',
                    'account' => 'required|email',
                    'password' => 'required|min:6',
                    'code' => 'required',
                ];
                break;
        }
        $validmessages = [
            'account_type.required' => '您必须选择一种重置密码的方式',
            'account.required' => '您必须输入您的帐号',
            'account.regex' => '手机号格式不正确，目前仅支持中国大陆地区的手机号，前面无需+86',
            'account.email' => '邮箱格式不正确',
            'password.min' => '新设置的密码最低为6位，谢谢',
            'password.required' => '您必须输入您的新密码',
            'code.required' => '验证码必须输入'
        ];
        validateParams($request->only('account_type', 'account', 'password', 'code'), $validrules, $validmessages);
        $account_field = $request->input('account_type') == 'phone' ? 'phone' : 'email';
        $getCode = Redis::get('changepassword:'.$account_field.':'.$request->input('account'));
        if (!$getCode || $getCode != $request->input('code')) {
            return errorJson('VERIFYCODE_FAILED');
        }
        $user = DB::table('ds_user_info')->where($account_field, e($request->input('account')))->first();
        if (!$user || $user->islock == UserConfig::UserAccountLocked) {
            // 如果没有这个用户或者用户已被管理员锁定
            return errorJson('USER_CANNOT_FOUND');
        }
        $newpassword = generatePassword(e($request->input('password')), '', true);
        DB::table('ds_user_info')->where('id', $user->id)->update([
          'password' => $newpassword['password'],
          'passsalt' => $newpassword['salt']
        ]);
           // 修改完成后立刻删除验证码
        Redis::del('changepassword:'.$account_field.':'.$request->input('account'));
        // 获取用户 Token
        $token = generateToken($user->nickname, $user->id);
        // 删除 Token
        Redis::del('user:'.$token);
        return successJson();
        
    }
    /**
     * @router /user/change/info
     * @params { nickname, intro, headimg,sex }
     * @response BaseResponse
     * @name 修改用户信息
     */
    public function changeUserInfo(Request $request)
    {
        $validrules = [
            'headimg' => 'required',
            'intro' => 'max:100',
            'nickname' => 'required',
            'sex' => 'required|numeric',
        ];
        $validmessages = [
            'headimg.required' => '您必须选择或上传一个头像',
            'intro.max' => '个人简介最大字数为100',
            'nickname.required' => '昵称不可为空',
            'sex.required' => '性别必须填写',
            'sex.numeric' => '性别选择错误'
        ];
        $changeNicknameBool = false;
        $changeHeadimgBool = false;
        validateParams($request->only('headimg', 'intro', 'nickname'), $validrules, $validmessages);
        $userInfo = $request->get('userInfo');
        // 判断这个用户是否为作者
        $user = User::find($userInfo['id']);
        if (!$user) {
            return errorJson('USER_CANNOT_FOUND');
        }
        $userChangeAuth = DB::table('ds_user_change_info_time')->where('userid', $user->id)->first();
        if ($user->nickname != $request->input('nickname')) {
            if ($userChangeAuth && $userChangeAuth->nickname > time()) {
                return errorJson('CHANGE_USER_INFO_MODIFY_TIME_LIMIT_DID_NOT_ARRIVE');
            }
            $nicknameCheck = DB::table('ds_user_info')->where('nickname', $request->input('nickname'))->first();
            if ($nicknameCheck) {
                return errorJson('USER_NICKNAME_IS_EXIST');
            }
            $authorInfo = DB::table('ds_book_info')->where('userid', $userInfo['id'])->where('issign', 1)->count();
            if ($authorInfo) {
                return errorJson('SIGNED_AUTHOR_CANNOT_CHANGE_USER_NICKNAME');
            }
            $changeNicknameBool = true;
        }
        if ($user->headimg != $request->input('headimg')) {
            if ($userChangeAuth && $userChangeAuth->avatar > time()) {
                return errorJson('CHANGE_USER_INFO_MODIFY_TIME_LIMIT_DID_NOT_ARRIVE');
            }
            $changeHeadimgBool = true;
        }
        if ($userChangeAuth) {
            $nextChangeHeadImgTimestamp = $changeHeadimgBool == true ? time() + UserConfig::UserChangeHeadimgSecond : $userChangeAuth->avatar;
            $nextChangeNicknameTimestamp = $changeNicknameBool == true ? time() + UserConfig::UserChangeNicknameSecond : $userChangeAuth->nickname;
        } else {
            $nextChangeHeadImgTimestamp = $changeHeadimgBool == true ? time() + UserConfig::UserChangeHeadimgSecond : 0;
            $nextChangeNicknameTimestamp = $changeNicknameBool == true ? time() + UserConfig::UserChangeNicknameSecond : 0;
        }
        try {
            DB::beginTransaction();
            $user->nickname = e($request->input('nickname'));
            $user->headimg = e($request->input('headimg'));
            $user->intro = e($request->input('intro'));
            $user->sex = $request->input('sex') == 0 ? 0 : 1;
            $user->save();
            if ($changeHeadimgBool || $changeNicknameBool) {
                DB::table('ds_book_comment')->where('userid', $user->id)->update([
                    'nickname' => e($request->input('nickname')),
                    'headimg' => e($request->input('headimg')),
                ]);
            }
            DB::table('ds_user_change_info_time')->updateOrInsert([
                'userid' => $user->id,
            ], [
                'avatar' => $nextChangeHeadImgTimestamp,
                'nickname' => $nextChangeNicknameTimestamp,
            ]);
            DB::commit();
            return successJson();
        } catch (\Throwable $th) {
            DB::rollBack();
            return errorJson();
        }       
    }
    public function userDeleteSelfAccount(Request $request)
    {
        $userInfo  = $request->get('userInfo');
        $user = User::find($userInfo['id']);
        if (!$user || $user->islock == UserConfig::UserAccountLocked) {
            return errorJson('USER_CANNOT_FOUND');
        }
        if ($user->gold != 0) {
            return errorJson('ACCOUNT_RESPONSE_NOT_NULL');
        } 
        $book = DB::table('ds_book_info')->where('userid', $user->id)->count();
        if ($book != 0) {
            return errorJson('USER_BOOK_NOT_NULL');
        }
        // 进入注销阶段
        try {
            DB::beginTransaction();
            // 将用户所有评论全部修改为[已注销]
            DB::table('ds_book_comment')->where('userid', $user->id)->update([
                'nickname' => '已注销'
            ]);
            // 将用户的手机号码注销/email注销/password修改成随机密码，islock变更为1
            // 此举是为了避免有通过userid来join ds_user_info ，获取用户昵称、头像信息的，如获取不到可能会报错，因此不能直接删除此用户
            DB::table('ds_user_info')->where('id', $user->id)->update([
                'phone' => null,
                'email' => null,
                'nickname' => '已注销',
                'password' => md5(Str::random(68)),
                'islock' => UserConfig::UserAccountLocked,
            ]);
            DB::commit();
            $userTokenData = generateToken($user->nickname, $user->id);
            // 清空用户登录状态
            Redis::del('user:'.$userTokenData);
            return successJson();
        } catch (\Throwable $th) {
            DB::rollBack();
            return errorJson();
        }
    }
    /**
     * @router /user/signin
     * @params {}
     * @response BaseResponse
     * @name 签到
     */
    public function signIn(Request $request)
    {
        $userInfo = $request->get('userInfo');
        $newRecord = true;
        $dateMonth = date('m', time());
        $dateDaliy = date('d', time());
        $signinTodayCache = Redis::get('usersign:'.$dateDaliy.':'.$userInfo['id']);
        if ($signinTodayCache) {
            return errorJson('CANNOT_SIGN_IN_REPEATEDLY');
        }
        // ds_user_daliy_sign 至少每年清空一次，因此只验证月份
        $signRecord = DB::table('ds_user_daliy_sign')
                        ->where('userid', $userInfo['id'])
                        ->where('month', $dateMonth)
                        ->first();
        if (!$signRecord) {
            $signidentifier = $dateDaliy;
            $continuous = 1;
        } else {
            $signidentifier = $signRecord->signlog.'|'.$dateDaliy;
            $signRecordArray = explode('|', $signRecord->signlog);
            if (in_array($dateDaliy, $signRecordArray)) {
                Redis::setex('usersign:'.$dateDaliy.':'.$userInfo['id'], 5 * 3600, 1);
                return errorJson('CANNOT_SIGN_IN_REPEATEDLY');
            }
            $signRecordLast = last($signRecordArray);
            if ($dateDaliy - $signRecordLast == 1) {
                $continuous = $signRecord->continuous + 1;
            } else {
                $continuous = 1;
            }
            if ($signRecord->continuous == 7) {
                // 当当前签到的日期等于7时，恢复到1天
                $continuous = 1;
            }
            $newRecord = false;
        }
        $userRole = getLevelInfo($userInfo['level']);
        if ($userRole == null) {
            return errorJson('SIGNIN_ILLEGAL');  
        }
        try {
            DB::beginTransaction();
            if ($newRecord) {
                DB::table('ds_user_daliy_sign')->insert([
                    'userid' => $userInfo['id'],
                    'signlog' => $signidentifier,
                    'continuous' => $continuous,
                    'month' => $dateMonth
                ]);
            } else {
                DB::table('ds_user_daliy_sign')->where('id', $signRecord->id)->update([
                    'continuous' => $continuous,
                    'signlog' => $signidentifier,
                ]);
            }
            DB::table('ds_user_info')->where('id', $userInfo['id'])->increment('experience', config('userexp.signin'), [ 'recompiao' => DB::raw('recompiao + '.$userRole->signin_recommended_ticket)]);
            if ($signRecord->continuous == UserConfig::ContinuousSignInThreshold && $userRole->signin_gold != 0) {
                DB::table('ds_user_info')->where('id', $userInfo['id'])->increment('gold', $userRole->signin_gold);
                
            }
            DB::commit();
            return successJson();
        } catch (\Throwable $th) {
            DB::rollBack();
            return errorJson('SIGNIN_ERROR');
        }
    }
    public function upgradeAuthor(Request $request)
    {
        $userInfo = $request->get('userInfo');
        $authorInfo = DB::table('ds_user_authorinfo')->where('userid', $userInfo[id])->first();
        if ($authorInfo) {
            return errorJson('UPGRADE_AUHTOR_FAILED');
        }
        $user = User::find($userInfo['id']);
        if (!$user || $user->islock == UserConfig::UserAccountLocked) {
            return errorJson('UPGRADE_AUHTOR_FAILED');
        }
        DB::table('ds_user_authorinfo')->insert([
            'userid' => $userInfo['id'],
            'agentid' => 0
        ]);
        return successJson();
    }
    public function updateEmailOrPhone(Request $request)
    {
        $userInfo = $request->get('userInfo');
        switch ($request->input('account_type')) {
            case 'phone':
                $validrules = [
                    'account_type' =>  'required',
                    'new_account' => 'required|regex:/(1)[0-9]{10}/|unique:ds_user_info,phone',
                    'verify_code' => 'required',
                ];
                break;
            default:
                $validrules = [
                    'account_type' =>  'required',
                    'new_account' => 'required|email|unique:ds_user_info,email',
                    'verify_code' => 'required',
                ];
                break;
        }
        $accountTypeArr = array('email', 'phone');
        if (!in_array($request->input('account_type'), $accountTypeArr)) {
            return errorJson('DENY_ALLOW_THIS_ACCOUNT_TYPE');
        }
        $validmessages = [
            'account_type.required' => '请选择一个您要修改的信息(手机号/邮箱)',
            'new_account.required' => '您必须输入一个新账号',
            'new_account.regex' => '手机号格式不正确，目前仅支持中国大陆的手机号',
            'new_account.email' => '邮箱格式不正确',
            'verify_code.required' => '验证码必须填写',
            'new_account.unique' => '新账号【'.$request->input('new_account').'】与他人重复'
        ];
        validateParams($request->only('account_type', 'new_account', 'verify_code'), $validrules, $validmessages);  
        $redisKey = 'change'.$request->input('account_type').':'.$request->input('new_account');
        $verifyCodeInfo = Redis::get($redisKey);
        if (!$verifyCodeInfo) {
            return errorJson('VERIFYCODE_NOT_FOUND');
        }
        if ($verifyCodeInfo != $request->input('verify_code')) {
            return errorJson('VERIFYCODE_FAILED');
        }
        $user = User::find($userInfo['id']);
        if (!$user || $user->islock == UserConfig::UserAccountLocked) {
            return errorJson('USER_CANNOT_FOUND');
        }
        switch ($request->input('account_type')) {
            case 'phone':
                DB::table('ds_user_info')->where('id', $user->id)->update([
                    'phone' => $request->input('new_account'),
                ]);
                break;
            default:
                DB::table('ds_user_info')->where('id', $user->id)->update([
                    'email' => $request->input('new_account'),
                ]);
                break;
        }
        $data = [
            'userid' => $user->id,
            'userip' => $request->ip(),
            'platform' => $request->header('platform') ? $request->header('platform') : 'Web',
            'accountType' => $request->input('account_type'),
            'isSuccess' => false,
            'changeInfo' => $request->input('account_type') == 'email' ? '[信息变更]'.$user->email.'变更为'.$request->input('new_account') : '[信息变更]'.$user->phone.'变更为'.$request->input('new_account'),
        ];
        UserLoginProcess::dispatch($data);
        Redis::del($redisKey);
        return successJson();
    }
    /**
     * @router /get/user/info
     * @params {}
     * @response { userinfo, log, weather, signInLog }
     * @name 个人中心信息获取（非敏感）
     */
    public function getUserInfo(Request $request)
    {
        $userInfo = $request->get('userInfo');
        $log = Cache::remember('user:log:'.$userInfo['id'], 3600 * 3, function() use($userInfo) {
            return DB::table('ds_user_login_record')->where('userid', $userInfo['id'])->limit(30)->orderBy('id', 'desc')->get();
        });
        $SignInRecord = Cache::remember('user:signin:log:'.$userInfo['id'], 3600 * 3, function() use($userInfo){
            return DB::table('ds_user_daliy_sign')->where('userid', $userInfo['id'])->where('month', date('m', time()))->first();
        });
        $getUserInfo = GaodeMap::getUserIpInfo($request->ip());
        $getWatherInfo = GaodeMap::getWeatherInfo($getUserInfo['adcode']);
        // 过滤敏感数据
        unset($userInfo['hash']);
        unset($userInfo['refresh_time']);
        $data = [
            'userinfo' => $userInfo,
            'log' => $log,
            'weather' => $getWatherInfo,
            'signInLog' => $SignInRecord,
        ];
        return successJson($data);
    }
   /**
     * @router /get/user/order/list
     * @params {}
     * @response { }
     * @name 获取个人订单
     */
    public function getUserOrder(Request $request)
    {
        $userInfo = $request->get('userInfo');
        $page = isset($_GET['page']) ? $_GET['page'] : 1;
        if ($page == 1) {
            $pageOffset = 0;
        } else {
            $pageOffset = ($page * 30) - 1;
        }
        
        $orderList = Cache::remember('user:order:'.$userInfo['id'].'_'.$page, 1800, function() use($userInfo, $pageOffset) {
            return DB::table('ds_pay_recharge')->where('userid', $userInfo['id'])->orderBy('id', 'desc')->limit(30)->offset($pageOffset)->select('id', 'userid', 'paymoney', 'orderno', 'thirdorderno', 'specialpaytype', 'payon as status', 'logtime', 'message_info')->get();
        });
        $data = [
            'order' => $orderList,
            'count' => count($orderList),
            'max_page_limit' => 30,
        ];
        return successJson($data);
    }
    public function getUserChapterOrder(Request $request)
    {
        $userInfo = $request->get('userInfo');
        $page = isset($_GET['page']) ? $_GET['page'] : 1;
        if ($page == 1) {
            $pageOffset = 0;
        } else {
            $pageOffset = ($page * 30) - 1;
        }
        $orderList = Cache::remember('user:chapter:order:list:'.$userInfo['id'].'_'.$page, 1800, function() use($userInfo, $pageOffset) {
            return DB::table('ds_order_chapter')->where('userid', $userInfo['id'])->orderBy('id', 'desc')->select('id', 'userid', 'bookid', 'chapterid', 'spendgold as gold', 'bookname', 'chaptername', 'logtime')->limit(30)->offset($pageOffset)->get();
        });
        $data = [
            'order' => $orderList,
            'count' => count($orderList),
            'max_page_limit' => 30,
        ];
        return successJson($data);
    }
}
