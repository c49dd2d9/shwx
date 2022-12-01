<?php

namespace App\Http\Middleware;

use Closure;
use App\Enums\UserConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Redis;

class UserAuthCheck
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
        // 处理白名单
        $currentUrl =  Route::current()->uri;
        $whiteUrl = [ 'api/read/chapter' ];
        $authorization = $request->header('Authorization');
        if (!$authorization && in_array($currentUrl, $whiteUrl)) {
            return $next($request);
        }
        $tokenValid = strpos($authorization, UserConfig::UserTokenDelimiter);
        if ($tokenValid > 0) {
            $tokenInfo = explode(UserConfig::UserTokenDelimiter, $authorization);
            $token = $tokenInfo[1];
            $hashCode = $tokenInfo[0];
            $userInfo = unserialize(Redis::get('user:'.$token));
            // 无法找到 Redis key
            if (!$userInfo) {
                return errorJson('LOGIN_STATUS_IS_INVALID');
            }
            // hash Code不符合
            if ($userInfo['hash'] != $hashCode) {
                Redis::del('user:'.$token);
                return errorJson('LOGIN_STATUS_IS_INVALID');  
            }
            // 如果当前时间大于refresh_time，则要求用户重新登录
            if (time() > $userInfo['refresh_time']) {
                Redis::del('user:'.$token);
                return errorJson('LOGIN_STATUS_IS_INVALID');
            }
            $userLevelHasCache = Redis::get('userlevel:'.$userInfo['id']);
            if ($userInfo['level'] < UserConfig::MaxLevel && !$userLevelHasCache) {
                $userRole = getLevelInfo($userInfo['level'] + 1);
                if ($userRole) {
                    $userDBInfo = DB::table('ds_user_info')->where('id', $userInfo['id'])->first();
                    if ($userDBInfo && $userDBInfo->experience >= $userRole->experience) {
                        DB::table('ds_user_info')->where('id', $userDBInfo->id)->update([
                            'level' => $userRole->level
                        ]);
                        $refreshTime = time() + UserConfig::UserTokenRefrshTime;
                        $userData = [
                            'id' => $userInfo['id'],
                            'nickname' => $userInfo['nickname'],
                            'avatar' => $userInfo['headimg'],
                            'isvip' => $userInfo['isvip'],
                            'level' => $userRole->level,
                            'hash' => $hashCode,
                            'refresh_time' => $refreshTime,
                        ];
                        Redis::setex('user:'.$token, UserConfig::UserTokenEfficient, serialize($userData));
                    }
                }
                Redis::setex('userlevel:'.$userInfo['id'], 6 * 3600, 1);
            }
            // 携带用户参数到 Controller
            $request->attributes->add(['userInfo' => $userInfo]);
            // 重置 Redis 过期时间
            Redis::expire('user:'.$token, UserConfig::UserTokenEfficient);
            return $next($request);
        } else {
            return errorJson('LOGIN_STATUS_IS_INVALID');
        }
    }
}
