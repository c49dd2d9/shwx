<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;
use App\Exceptions\ShanHaiCPException;
use Illuminate\Support\Facades\Cache;

class CPSign
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
        $key = isset($_GET['key']) ? $_GET['key'] : '';
        if ($key == '') {
            throw new ShanHaiCPException('无效的认证');
        }
        $token = isset($_GET['token']) ? $_GET['token'] : '';
        $channelInfo = Cache::remember('book_channel:'.$key, 3600 * 24, function()use($key) {
            return DB::table('ds_channel_info')->where('channelkey', $key)->first();
        });
        if (!$channelInfo) {
            throw new ShanHaiCPException('无效的合作方认证，请联系工作人员获取密钥对');
        }
        if ($channelInfo->endtime != null && strtotime($channelInfo->endtime) < time()) {
            throw new ShanHaiCPException('合作已过期');
        }
        $request->attributes->add(['channelInfo' => $channelInfo, 'param_key' => $key, 'param_token' => $token]);
        return $next($request);
    }
}
