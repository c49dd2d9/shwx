<?php

namespace App\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use App\Models\Topic;
use App\Models\Reply;
use Illuminate\Support\Facades\DB;
use App\Exceptions\AdminApiExcption;
use App\Http\Controllers\Controller;

class BoardController extends Controller
{
    public function deleteOrRecoverTopic($topic_id, $status)
    {
        $topic = Topic::find($topic_id);
        if (!$topic) {
            throw new AdminApiExcption('TOPIC_DATA_NOT_FOUND');
        }
        $topic->isdelete = $status == 1 ? 0 : 1;
        $topic->save();
        $replyTableName = Reply::suffix($topic->id);
        DB::table($replyTableName)->where('topicid', $topic->id)->update([
            'is_delete' => $status == 1 ? 0 : 1,
        ]);
        return successJson();
    }
    public function deleteOrRecoverReply($topic_id, $reply_id)
    {
        $topic = Topic::find($topic_id);
        if (!$topic || $topic->isdelete == 1) {
            throw new AdminApiExcption('TOPIC_DATA_NOT_FOUND');
        }
        $replyTableName = Reply::suffix($topic->id);
        $reply = DB::table($replyTableName)->where('id', $reply_id)->first();
        if (!$reply) {
            throw new AdminApiException('REPLY_DATA_NOT_FOUND');
        }
        $newStatus = $reply->is_delete == 1 ? 0 : 1;
        DB::table($replyTableName)->where('id', $reply->id)->update([
            'is_delete' => $newStatus
        ]);
        return successJson();
    }
    public function changeTopicInfo(Request $request)
    {
       $validrules = [
        'module_name' => 'required',
        'status' => 'required',
        'change_field' => 'required',
        'topicid' => 'required|numeric',
       ];
       $allowModuleNameList = [ 'change_topic_top', 'change_topic_title_color' ];
       if (!in_array($request->input('module_name'), $allowModuleNameList)) {
         throw new AdminApiException('BBS_MODULE_NAME_DENY_ALLOW');
       }
       $topic = Topic::find($request->input('topicid'));
       if (!$topic || $topic->isdelete == 1) {
        throw new AdminApiException('TOPIC_DATA_NOT_FOUND');
       }
       switch ($request->input('module_name')) {
        case 'change_topic_top':
            $topicTopInfo = DB::table('ds_topic_top_info')->where('topicid', $topic->id)->first();
            if (!$topicTopInfo) {
                DB::table('ds_topic_top_info')->insert([
                    'boardid' => $topic->boardid,
                    'topicid' => $topic->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('ds_topic_top_info')->where('id', $topicTopInfo->id)->delete();
            }
            return successJson();
            break;
        case 'change_topic_title_color':
            $topic->thread_color = $request->input('status') == true ? $request->input('change_field') : null;
            $topic->save();
            return successJson();
            break;
        default:
            throw new AdminApiException('BBS_MODULE_NAME_DENY_ALLOW');
            break;
       }
    }
    
}
