<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class CheckAuthor
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     * @name 作者身份验证中间件
     */
    public function handle($request, Closure $next)
    {
        $userInfo = $request->get('userInfo');
        $authorKey = 'author:'.md5($userInfo['id']);
        $authorCacheInfo = Redis::get($authorKey);
        if (!$authorCacheInfo) {
            $authorInfo = DB::table('ds_user_authorinfo')->where('userid', $userInfo['id'])->first();
            if ($authorInfo) {
                Redis::setex($authorKey, 3 * 3600, 1);
                $request->attributes->add(['authorInfo' => $authorInfo]);
                return $next($request);
            } else {
                Redis::setex($authorKey, 3 * 3600, 0);
                return errorJson('AUTHOR_IDENTITY_INVALID');
            }
        } else {
            if ($authorCacheInfo == 1) {
                $request->attributes->add(['authorInfo' => $authorCacheInfo]);
                return $next($request);
            } else {
                return errorJson('AUTHOR_IDENTITY_INVALID');
            }
        }
    }
}
