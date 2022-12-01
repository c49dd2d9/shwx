<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\Topic;
use App\Enums\BoardConfig;
use Illuminate\Support\Facades\Cache;
use App\Models\Reply;
use App\Enums\BanConfig;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

// 主题Controller
class TopicController extends Controller
{
    /**
     * @router /create/topic
     * @params { title, content, board_id }
     * @response BaseResponse
     * @name 添加新主题
     */
    public function create(Request $request)
    {
        $validrules = [
            'title' => 'required|max:120',
            'content' => 'required|max:3000',
            'board_id' => 'required|numeric',
        ];
        $validmessages = [
            'title.required' => '请输入标题',
            'content.required' => '请输入内容',
            'title.max' => '标题字数不得大于120',
            'content.max' => '内容字数不得大于3000'
        ];
        validateParams($request->only('title', 'content', 'board_id'), $validrules, $validmessages);
        $userInfo = $request->get('userInfo');
        $topic = new Topic;
        $topic->title = e($request->input('title'));
        $topic->content = e($request->input('content'));
        $topic->nickname = $userInfo['nickname'];
        $topic->userid = $userInfo['id'];
        $topic->boardid = $request->input('board_id');
        $topic->replies = 0;
        $topic->avatar = $userInfo['avatar'];
        $topic->level = $userInfo['level'];
        $topic->isdelete = 0;
        $topic->lastpost = time();
        $topic->save();
        return successJson();
    }
    /**
     * @router /topic/list/{id}
     * @params {}
     * @response BaseResponse
     * @name 主题贴列表
     */
    public function list($id)
    {
        if ($id == 0) {
            return errorJson('BOARD_IS_OFF');
        }
        $page = isset($_GET['page']) ? $_GET['page'] : 1;
        $board = DB::table(BoardConfig::BoardTableName)->where('id', $id)->first();
        if (!$board || $board->status == 2) {
            return errorJson('BOARD_IS_OFF');
        }
        $topicTopIdList = DB::table('ds_topic_top_info')->where('boardid', $board->id)->pluck('id');
        $topicTopList = Cache::remember('topic:top:list', 1800, function()use($topicTopIdList) {
            return DB::table('ds_topic_info')->whereIn('id', $topicTopIdList)->select('id', 'title', 'nickname', 'replies', 'lastpost', 'thread_color', 'created_at')->get();
        });
        $topicList = Cache::remember('topic:list:'.$id.'_'.$page, 180, function()use($id, $topicTopIdList) {
            return Topic::where('boardid', $id)->where('is_delete', 0)->whereNotIn('id', $topicTopIdList)->select('id', 'title', 'nickname', 'replies', 'lastpost', 'thread_color', 'created_at')->orderBy('lastpost', 'desc')->paginate(BoardConfig::TopicPageItem);
        });
        $data = [
            'top' => $topicTopList,
            'topic' => $topicList
        ];
        return successJson($data);
    }
    /**
     * @router /topic/show
     * @params {}
     * @response BaseResponse
     * @name 查看主题贴
     */
    public function show(Request $request)
    {
        $id = $request->input('id') ? $request->input('id') : 0;
        if ($id == 0) {
            return errorJson('TOPIC_IS_DELETED');
        }
        $topic = Topic::find($id);
        if (!$topic || $topic->is_delete == 1) {
            return errorJson('TOPIC_IS_DELETED');
        }
        $page = isset($_GET['page']) ? $_GET['page'] : 1;
        $replyTableName = Reply::suffix($topic->id);
        $replyList = Cache::remember('topic:reply:cache:'.$topic->id.'_'.$page, 180, function()use($replyTableName, $topic) {
            return DB::table($replyTableName)->where('topicid', $topic->id)->paginate(BoardConfig::ReplyPageItem);
        });
        $data = [
            'topic' => $topic,
            'replies' => $replyList
        ];
        return successJson($data);
    }
}
