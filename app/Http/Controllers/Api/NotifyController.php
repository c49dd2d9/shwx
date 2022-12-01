<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

// 通知Controller
class NotifyController extends Controller
{
    /**
     * @router /get/unread/new/notify
     * @params 
     * @response {  }
     * @name 获取用户未读的信息
     */
    public function getUnreadNotifyCount(Request $request)
    {
        $userInfo = $request->get('userInfo');
        $hasUnReadComment = Redis::get('user:comment:unread:'.$userInfo['id']);
        $hasUnReadNotify = Redis::get('user:notify:unread:'.$userInfo['id']);
        if ($hasUnReadComment) {
            $comment = unserialize($hasUnReadComment);
        } else {
            $comment = DB::table('ds_unread_comment')->where('userid', $userInfo['id'])->pluck('id');
        }
        if ($hasUnReadNotify) {
            $notify = unserialize($hasUnReadNotify);
        } else {
            $notify = DB::table('ds_user_notify_data')->where('userid', $userInfo['id'])->where('read_status', 0)->pluck('id');
        }
        if (count($comment) > 0 && !$hasUnReadComment) {
            Redis::setex('user:comment:unread:'.$userInfo['id'], 60 * 15, serialize($comment));
        }
        if (count($notify) > 0 && !$hasUnReadNotify) {
            Redis::setex('user:notify:unread:'.$userInfo['id'], 60 * 15, serialize($notify));
        }
        $data = [
            'comment_id_lsit' => $comment,
            'notify_id_list' => $notify,   
        ];
        return successJson($data);
    }
    /**
     * @router /get/notify/list
     * @params 
     * @response {  }
     * @name 获取通知列表
     */
    public function getNotifyList(Request $request)
    {
        $userInfo = $request->get('userInfo');
        if (Redis::get('user:notify:unread:'.$userInfo['id'])) {
            Cache::forget('user:notify:'.$userInfo['id'].'_list');
        }
        $notify = Cache::remember('user:notify:'.$userInfo['id'].'_list', 1800, function()use($userInfo) {
            return  DB::table('ds_user_notify_data')->where('userid', $userInfo['id'])->orderBy('id', 'desc')->select('id', 'title', 'content', 'created_at')->get();
        });
        if (Redis::get('user:notify:unread:'.$userInfo['id'])) {
            DB::table('ds_user_notify_data')->where('userid', $userInfo['id'])->where('read_status', 0)->update([
                'read_status' => 1
            ]);
            Redis::del('user:notify:unread:'.$userInfo['id']);
        }
        return successJson($notify);
    }
}
