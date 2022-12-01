<?php

namespace App\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use App\Exceptions\AdminApiException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;

class NotifyController extends Controller
{
    /**
     * @router /admin/create/notify
     * @params { title, content, user_id_list, is_all }
     * @response {  }
     * @name 发送站内通知
     */
    public function create(Request $request)
    {
        $validrules = [
            'title' => 'required|max:30',
            'content' => 'required|min:5|max:2000',
            'user_id_list' => 'required',
            'is_all' => 'required|boolean'
        ];
        validateParams($request->only('title', 'content', 'user_id_list', 'is_all'), $validrules);
        if (!is_array($request->input('user_id_list')) && $request->input('is_all') != true) {
            throw new AdminApiException('USER_ID_LIST_NOT_A_LIST');
        }
        $title = $request->input('title');
        $content = $request->input('content');
        $allUUID = Str::random(32);
        if ($request->input('is_all') != true) {
            DB::table('ds_user_info')->whereIn('id', $request->input('user_id_list'))->orderBy('id', 'asc')->chunk(50, function($user)use($title, $content,$allUUID) {
                $data = [];
                $adminData = [];
                foreach ($user as $userItem) {
                    $insertData = [
                        'userid' => $userItem->id,
                        'title' => $title,
                        'content' => $content,
                        'type' => 1,
                        'read_status' => 0,
                        'all_uuid' => $allUUID,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    array_push($data, $insertData);
                }
                DB::table('ds_user_notify_data')->insert($data);
            });
            DB::table('ds_admin_search_notify_data')->insert([
                'title' => $title,
                'content' => $content,
                'all_uuid' => $allUUID,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $userId = DB::table('ds_user_info')->orderBy('id', 'asc')->chunk(50, function($user)use($title, $content, $allUUID) {
                $data = [];
                foreach ($user as $userItem) {
                    $insertData = [
                        'userid' => $userItem->id,
                        'title' => $title,
                        'content' => $content,
                        'type' => 1,
                        'read_status' => 0,
                        'all_uuid' => $allUUID,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    array_push($data, $insertData);
                }
                DB::table('ds_user_notify_data')->insert($data);
            });
            DB::table('ds_admin_search_notify_data')->insert([
                'title' => $title,
                'content' => $content,
                'all_uuid' => $allUUID,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        Cache::forget('admin:notify:list');
        return successJson();
    }
    /**
     * @router /admin/delete/notify
     * @params { notify_id }
     * @response {  }
     * @name 删除站内通知
     */
    public function deleteNotify(Request $request)
    {
        $validrules = [
            'notify_id' => 'required',
        ];
        DB::table('ds_user_notify_data')->where('all_uuid', e($request->input('notify_id')))->delete();
        DB::table('ds_admin_search_notify_data')->where('all_uuid', e($request->input('notify_id')))->delete();
        Cache::forget('admin:notify:list');
        return successJson();
    }
    /**
     * @router /admin/get/notify/list
     * @params {}
     * @response {  }
     * @name 获取站内通知
     */
    public function getNotifyList()
    {
        $data = Cache::remember('admin:notify:list', 7 * 3600, function() {
            return DB::table('ds_admin_search_notify_data')->get();
        });
        return successJson($data);
    }
    /**
     * @router /admin/change/notify/info
     * @params { notify_id, title, content }
     * @response {  }
     * @name 修改站内通知内容
     */
    public function changeNotifyInfo(Request $request)
    {
        $validrules = [
            'notify_id' => 'required',
            'title' => 'required|max:30',
            'content' => 'required|max:2000|min:5'
        ];
        validateParams($request->only('notify_id', 'title', 'content'), $validrules);
        $notifyInfo = DB::table('ds_admin_search_notify_data')->where('all_uuid', $request->input('notify_id'))->first();
        if (!$notifyInfo) {
            throw new AdminApiException('NOTIFY_CANNOT_FOUND');
        }
        try {
            DB::beginTransaction();
            DB::table('ds_admin_search_notify_data')->where('id', $notifyInfo->id)->update([
                'title' => $request->input('title'),
                'content' => $request->input('content'),
            ]);
            DB::table('ds_user_notify_data')->where('all_uuid', $notifyInfo->all_uuid)->update([
                'title' => $request->input('title'),
                'content' => $request->input('content'),
            ]);
            DB::commit();
            Cache::forget('admin:notify:list');
            return successJson();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw new AdminApiException('DEFAULT_ERROR');
        }
    }
}
