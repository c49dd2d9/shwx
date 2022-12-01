<?php

namespace App\Http\Controllers\Api\Reader;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Http\Controllers\Controller;

class AuthorController extends Controller
{
    /**
     * 1、关注用户
     * 2、取消关注
     * 3、关注列表
     */
    public function foucsUser(Request $request)
    {
        $validrules = [
            'user_id'  => 'required|numeric',
        ];
        validateParams($request->only('user_id'), $validrules);
        $userInfo = $request->get('userInfo');
        $userLevel = getLevelInfo($userInfo['level']);
        $user = User::find($request->input('user_id'));
        if (!$user) {
            return errorJson();
        }
        $foucsCount = DB::select('EXPLAIN select * from ds_user_foucs_author where userid='.$userInfo['id']);
        if ($userLevel->focus_count <= $foucsCount[0].rows) {
            return errorJson();
        }
        DB::table('ds_user_foucs_author')->insert([
            'userid' => $userInfo['id'],
            'authorid' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return successJson();
    }

    public function deleteFoucs(Request $request)
    {
       $validrules = [
        'id_list' => 'reuqired',
       ];
       validateParams($request->only('id_list'), $validrules);
       if (!is_array($request->input('id_list'))) return errorJson();
       if (count($request->input('id_list')) > 10) return errorJson();
       $userInfo = $request->get('userInfo');
       $userFoucsList = DB::table('ds_user_foucs_author')->whereIn('id', $request->input('id_list'))->select('id', 'userid')->get();
       $data = [];
       foreach($userFoucsList as $item) {
        if ($item->userid == $userInfo['id']) {
            $newData = $item->id;
            array_push($data, $newData);
        }
       }
       DB::table('ds_user_foucs_author')->whereIn('id', $data)->delete();
       $returnData = [
        'list' => $request->input('id_list'),
        'delete_list' => $data,
       ];
       return successJson($returnData);
    }
    public function foucsList(Request $request)
    {
        $userInfo = $request->get('userInfo');
        $page = isset($_GET['page']) ? $_GET['page'] : 1;
        $pageLimit = 30;
        $pageOffset = ($page * $pageLimit) - 1;
        $data = DB::table('ds_user_foucs_author')->where('ds_user_foucs_author.userid', $userInfo['id'])->leftJoin('ds_user_info', 'ds_user_foucs_author.authorid', '=', 'ds_user_info.id')->leftJoin('ds_user_action_record', 'ds_user_info.recordid', '=', 'ds_user_action_record.id')->select('ds_user_action_record.created_at', 'ds_user_action_record.content', 'ds_user_info.nickname', 'ds_user_info.headimg', 'ds_user_info.id', 'ds_user_foucs_author.id as foucsid')->limit($pageLimit)->offset($pageOffset)->orderBy('id', 'desc')->get();
        $userIdData = [];
        foreach ($data as $item) {
            $newData = $item->userid;
            array_push($data, $userIdData);
        }
    }
    public function foucsListPublic($id)
    {
        $data = Cache::remember('foucs:user_'.$id, 1800, function() use($id) {
            return DB::table('ds_user_foucs_author')->where('ds_user_foucs_author.userid', $id)->leftJoin('ds_user_info', 'ds_user_foucs_author.authorid', '=', 'ds_user_info.id')->leftJoin('ds_user_action_record', 'ds_user_info.recordid', '=', 'ds_user_action_record.id')->select('ds_user_action_record.created_at', 'ds_user_action_record.content', 'ds_user_info.nickname', 'ds_user_info.headimg', 'ds_user_info.id', 'ds_user_foucs_author.id as foucsid')->limit(10)->orderBy('id', 'desc')->get(); 
        });
        return $data;
    }
    public function fanListPublic($id)
    {
        $data = Cache::remember('foucs:user_'.$id, 1800, function() use($id) {
            return DB::table('ds_user_foucs_author')->where('ds_user_foucs_author.authorid', $id)->leftJoin('ds_user_info', 'ds_user_foucs_author.authorid', '=', 'ds_user_info.id')->leftJoin('ds_user_action_record', 'ds_user_info.recordid', '=', 'ds_user_action_record.id')->select('ds_user_action_record.created_at', 'ds_user_action_record.content', 'ds_user_info.nickname', 'ds_user_info.headimg', 'ds_user_info.id', 'ds_user_foucs_author.id as foucsid')->limit(10)->orderBy('id', 'desc')->get(); 
        });
        return $data;
    }

}
