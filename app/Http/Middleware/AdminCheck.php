<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Exceptions\AdminApiException;
use App\Jobs\WriteAdminLog;
use Illuminate\Support\Facades\Route;

class AdminCheck
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
        // 获取当前url
        $currentUrl = $request->path();
        $userInfo = $request->get('userInfo');
        $userGroupInfo = Cache::remember('admin_group_info_'.$userInfo['id'], 3600, function()use($userInfo) {
            return DB::table('ds_user_groupdata')->where('userid', $userInfo['id'])->pluck('groupid');
        });
        $bbsGroupUrl = [];
        $adminGroupId = 1;
        $bbsGroupId = 4;
        if (in_array($currentUrl, $bbsGroupUrl)) {
            if (!in_array($bbsGroupId, $userGroupInfo) || !in_array($adminGroupId, $userGroupInfo)) {
                WriteAdminLog::dispatch([
                    'user_id' => $userInfo['id'],
                    'url' => $currentUrl,
                    'is_success' => 'No'
                ]);
                throw new AdminException('ADMIN_AUTH_CHECK_FAILED');
            }
        } else {
            if (!in_array($adminGroupId, $userGroupInfo)) {
                WriteAdminLog::dispatch([
                    'user_id' => $userInfo['id'],
                    'url' => $currentUrl,
                    'is_success' => 'No'
                ]);
                throw new AdminException('ADMIN_AUTH_CHECK_FAILED');
            }
        }
        WriteAdminLog::dispatch([
            'user_id' => $userInfo['id'],
            'url' => $currentUrl,
            'is_success' => 'Yes'
        ]);
        return $next($request);
    }
}
