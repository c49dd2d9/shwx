<?php

namespace App\Http\Middleware;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Redis;
use App\Enums\BanConfig;
use Closure;

class UserBanCheck
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
        $boardUrlList = [ 'api/create/topic', 'api/create/reply' ]; // 需要判断用户是否被封禁的论坛url
        $commentUrlList = [ 'api/create/comment' ]; //需要判断用户是否被封禁的评论url
        $currentUrl = Route::current()->uri;
        $userInfo = $request->get('userInfo');
        $userBoardBanInfo =  unserialize(Redis::get('ban:user:board:'.$userInfo['id']));
        $userCommentBanInfo = unserialize(Redis::get('ban:user:comment:'.$userInfo['id']));
        if (in_array($currentUrl, $boardUrlList)) {
            if ($userBoardBanInfo) {
                if ($userBoardBanInfo['expired'] > time()) {
                    return errorJson('USER_COMMENT_OR_BOARD_AUTH_BANNED', $userBoardBanInfo);
                } else {
                    return $next($request);
                }
            } else {
                $banInfo = DB::table('ds_ban_user')
                               ->where('userid', $userInfo['id'])
                               ->where('moduleid', BanConfig::BanBoard)
                               ->first();
                if ($banInfo && $banInfo->expired_time > time()) {
                    $userBoardBanInfoCache = [
                        'userid' => $banInfo->userid,
                        'type' => 'board',
                        'expired' => $banInfo->expired_time,
                    ];
                    Redis::setex('ban:user:board:'.$banInfo->userid, 3600, serialize($userBoardBanInfoCache));
                    return errorJson('USER_COMMENT_OR_BOARD_AUTH_BANNED', $userBoardBanInfoCache);
                } else {
                    $userBoardBanInfoCache = [
                        'userid' => $userInfo['id'],
                        'type' => 'board',
                        'expired' => -1,
                    ];
                    Redis::setex('ban:user:board:'.$banInfo->userid, 1800, serialize($userBoardBanInfoCache));
                    return $next($request);
                }
            }
        }
        if (in_array($currentUrl, $commentUrlList)) {
            if ($userCommentBanInfo) {
                if ($userCommentBanInfo['expired'] > time()) {
                    return errorJson('USER_COMMENT_OR_BOARD_AUTH_BANNED', $userCommentBanInfo);
                } else {
                    return $next($request);
                }
            } else {
                $banInfo = DB::table('ds_ban_user')
                                ->where('userid', $userInfo['id'])
                                ->where('moduleid', BanConfig::BanComment)
                                ->first();
                if ($banInfo && $banInfo->expired_time > time()) {
                    $userCommentBanInfoCache = [
                        'userid' => $banInfo->userid,
                        'type' => 'comment',
                        'expired' => $banInfo->expired_time,
                    ];
                    Redis::setex('ban:user:comment:'.$banInfo->userid, 3600, serialize($userCommentBanInfoCache));
                    return errorJson('USER_COMMENT_OR_BOARD_AUTH_BANNED', $userCommentBanInfoCache);
                } else {
                    $userCommentBanInfoCache = [
                        'userid' => $userInfo['id'],
                        'type' => 'comment',
                        'expired' => -1,
                    ];
                    Redis::setex('ban:user:comment:'.$banInfo->userid, 1800, serialize($userCommentBanInfoCache));
                    return $next($request);
                }
            } 
        }
    }
}
