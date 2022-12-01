<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\Reply;
use App\Enums\BoardConfig;
use App\Enums\BanConfig;
use App\Models\Topic;
use Illuminate\Support\Facades\Redis;
use App\Http\Controllers\Controller;

// 回复Controller
class ReplyController extends Controller
{
    /**
     * @router /create/reply
     * @params {  content, topic_id }
     * @response BaseResponse
     * @name 添加新回复
     */
    public function create(Request $request)
    {
        $userInfo = $request->get('userInfo');
        $userFirewall = Redis::get(BoardConfig::ReplyFireWallRedisSuffix.$userInfo['id']) ? Redis::get(BoardConfig::ReplyFireWallRedisSuffix.$userInfo['id']) : 0;
        if ($userFirewall >= BoardConfig::ReplyFireWallCount) {
            // 如果一分钟里回帖数大于设置的灌水频率
            return errorJson('REPLY_TO_FAST');
        }
        $validrules = [
            'topic_id' => 'required|numeric',
            'content' => 'required|max:3000',
        ];
        $validmessages = [
            'topic_id.required' => '回复的主题id不得为空',
            'content.required' => '请输入内容',
        ];
        validateParams($request->only('topic_id', 'content'), $validrules, $validmessages); 
        $topicId = $request->input('topic_id');
        $tableName = Reply::suffix($topicId);
        $topicInfo = Topic::find($topicId);
        $lastPage = ceil($topicInfo->replies / BoardConfig::ReplyPageItem);
        if ($lastPage == 0) {
            // 如果得到了0.01/0.001之类的数字，ceil运算结果也会得到0，因此要赋值为1
            $lastPage = 1;
        }
        if (!$topicInfo || $topicInfo->is_delete == 1) {
            return errorJson('TOPIC_IS_DELETED');
        }        
        try {
            DB::beginTransaction();
            // 新增回帖信息
            DB::table($tableName)->insert([
                'topicid' => $topicInfo->id,
                'boardid' => $topicInfo->boardid,
                'userid' => $userInfo['id'],
                'nickname' => $userInfo['nickname'],
                'content' => $request->input('content'),
                'avatar' => $userInfo['avatar'],
                'level' => $userInfo['level'],
                'floor' => $topicInfo->replies + 1,
                'is_delete' => 0,
                'created_at' => date('Y-m-d H:i:s', time()),
                'updated_at' => date('Y-m-d H:i:s', time()),
            ]);
            // 主题回帖数 + 1
            DB::table('ds_topic_info')->where('id', $id)->increment('replies');
            DB::commit();
            // redis 记录用户回帖频率
            if ($userFirewall <= BoardConfig::ReplyFireWallCount) {
                Redis::setex(BoardConfig::ReplyFireWallRedisSuffix.$userInfo['id'], BoardConfig::ReplyRedisTTL, $userFirewall + 1);                
            }
            // 删除最后一页的Cache
            Cache::forget('topic:reply:cache:'.$topicInfo->id.'_'.$lastPage);
            return successJson();
        } catch (\Throwable $th) {
            DB::rollBack();
            $errorId = generateErrorLog('回帖失败',$th->getMessage(), $request->all(), '/create/reply');
            return errorJson('USER_REGISTER_FAILED', $errorId);
        }
    }
}
