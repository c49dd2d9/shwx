<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use App\Enums\BookConfig;
use App\Enums\ChapterConfig;
use App\Enums\BanConfig;
use App\Jobs\SendCommentReplyNotify;
use App\Enums\CommentConfig;
use App\Http\Controllers\Controller;
use App\Jobs\LikeComment;

// 评论Controller
class CommentController extends Controller
{
    /**
     * @router /create/comment
     * @params { bookid, content, chapterid, pid }
     * @response BaseResponse
     * @name 添加评论
     */
    public function create(Request $request)
    {
        $userInfo = $request->get('userInfo');
        $validrules = [
            'bookid' => 'required|numeric',
            'content' => 'required|max:2000',
            'chapterid' => 'required|numeric',
            'pid' => 'numeric',
        ];
        $validmessages = [
            'content.required' => '您必须输入评论内容',
            'content.max' => '评论字数不得大于2000'
        ];
        validateParams($request->only('bookid', 'content', 'chapterid', 'pid'), $validrules, $validmessages);
        $userInputCommentInfo = $request->all();
        $bookInfo  = DB::table('ds_book_info')->where('id', $userInputCommentInfo['bookid'])->first();
        if (!$bookInfo || $bookInfo->publishstatus != BookConfig::BookNormalPublishstatus) {
            return errorJson('BOOK_NOT_FOUND');
        }
        $chapterInfo = DB::table('ds_book_chapter')->where('id', $userInputCommentInfo['chapterid'])->first();
        $longComment = comment_count_word($userInputCommentInfo['content']) >= CommentConfig::LongCommentSize ? 1 : 0;
        
        if (!$chapterInfo || $chapterInfo->status == ChapterConfig::ChapterNormalPublishstatus) {
            return errorJson('CHAPTER_NOT_FOUND');
        }
        if ($userInputCommentInfo['pid']) {
            $commentInfo = DB::table('ds_book_comment')->where('id', $userInputCommentInfo['pid'])->first();
            if (!$commentInfo) {
                return errorJson('FATHER_COMMENT_NOT_FOUND');
            }
        }
        $giftId = $userInputCommentInfo['gift_id'] ? $userInputCommentInfo['gift_id'] : 0;
        if ($giftId) {
            if ($bookInfo->issign == 0) {
                return errorJson('BOOK_NOT_SIGN_CANNOT_GIFT');
            }
            $user = DB::table('ds_user_info')->where('id', $userInfo['id'])->first();
            if (!$user) {
                // 无此用户
                return errorJson('ILLEGAL_OPERATION');
            }
            $giftInfo = DB::table('ds_gift_info')->where('id', $giftId)->first();
            if (!$giftInfo) {
                // 没有这个礼物信息
                return errorJson('ILLEGAL_OPERATION');
            }
            if ($giftInfo->price > $user->gold) {
                // 礼物售价大于当前用户所持有的虚拟货币
                return errorJson('GIFT_GOLD_INSUFFICIENT');
            }
            $bookSignInfo = DB::table('ds_book_signinfo')->where('bookid', $book->id)->first();
            if (!$bookSignInfo || $bookSignInfo->signstatus) {
                return errorJson('SIGNSTATUS_ABNORMAL');
            }
            $authorDividend = $giftInfo->price * ($bookSignInfo->giftfcrate / 100); // 作者分红
            $data = [
                'userInfo' => $user->toArray(),
                'giftInfo' => $giftInfo->toArray(),
                'bookInfo' => $book->toArray(),
                'is_long' => $longComment,
                'userInputInfo' => $userInputCommentInfo,
                'author_dividend' => $authorDividend
            ];
        } else {
            $commentId = DB::table('ds_book_comment')->insertGetId([
                'nickname' => $userInfo['nickname'],
                'headimg' => $userInfo['avatar'],
                'userid' => $userInfo['id'],
                'is_long' => $longComment,
                'bookid' => $userInputCommentInfo['bookid'],
                'chapterid' => $userInputCommentInfo['chapterid'],
                'pid' => $userInputCommentInfo['pid'],
                'content' => e($userInputCommentInfo['content']),
                'logtime' => date('Y-m-d H:i:s', time()),
                'status' => 2
            ]);
        }
        $sendSMSToAuthor = [
            'userid' => $bookInfo->userid,
            'id' => $commentId,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        SendCommentReplyNotify::dispatch($sendSMSToAuthor);
        return successJson();
    }
    /**
     * @router /get/book/comment
     * @params { bookid, is_long_comment }
     * @response BaseResponse
     * @name 获取作品评论
     */
    public function getBookComment(Request $request)
    {
        $validrules = [
            'bookid' => 'required|numeric',
            'is_long_comment' => 'required',
        ];
        $validmessages = [
            'bookid.required' => '您必须选择一本书籍',
            'is_long_comment.required' => '参数缺失' 
        ];
        validateParams($request->only('bookid', 'is_long_comment'), $validrules, $validmessages);
        $bookId = $request->input('bookid');
        $pageId = isset($_GET['page']) ? $_GET['page'] : 1;
        if ($request->input('is_long_comment') == 1) {
            $DBCondition = [
                'ds_book_comment.bookid' => $bookId,
                'ds_book_comment.is_long' => 1,
                'ds_book_comment.pid' => 0,
                'ds_book_comment.status' => 2,
            ];
        } else {
            $DBCondition = [
                'ds_book_comment.bookid' => $bookId,
                'ds_book_comment.pid' => 0,
                'ds_book_comment.status' => 2
            ];
        }
        $commentInfo = DB::table('ds_book_comment')->where($DBCondition)->orderBy('ds_book_comment.id', 'desc')->leftJoin('ds_user_info', 'ds_book_comment.userid', '=', 'ds_user_info.id')->select('ds_book_comment.*', 'ds_user_info.level', 'ds_user_info.nickname', 'ds_user_info.headimg')->paginate(CommentConfig::BookCommentPaginate);
        $fatherId = [];
        foreach($commentInfo as $CI) {
            array_push($fatherId, $CI->id);
        }
        $commentLikeList = Cache::remember('book:comment:like:list:'.$bookId.'_'.$pageId, 30 * 30, function() use($fatherId) {
            return DB::table('ds_comment_dianzan')->whereIn('comment_id', $fatherId)->select('id', 'user_id', 'comment_id')->get();
        });
        $commentReplyInfo = DB::table('ds_book_comment')->whereIn('ds_book_comment.pid', $fatherId)->leftJoin('ds_user_info', 'ds_book_comment.userid', '=', 'ds_user_info.id')->select('ds_book_comment.*','ds_user_info.level', 'ds_user_info.nickname', 'ds_user_info.headimg')->get();
        $data = [
            'comment' => $commentInfo,
            'reply' => $commentReplyInfo,
        ];
        return successJson($data);
    }
    /**
     * @router /get/chapter/comment
     * @params { chapterid }
     * @response BaseResponse
     * @name 获取章节评论
     */
    public function getChapterComment(Request $request)
    {
        $chapterId = $request->input('chapterid') ? $request->input('chapterid') : 0;
        if ($chapterId == 0) {
            return errorJson('COMMENT_PARAM_MISSING');
        }
        $commentInfo = DB::table('ds_book_comment')->where('ds_book_comment.chapterid', $chapterId)->where('ds_book_comment.status', 2)->orderBy('id', 'desc')->leftJoin('ds_user_info', 'ds_book_comment.userid', '=', 'ds_user_info.id')->select('ds_book_comment.*','ds_user_info.level', 'ds_user_info.nickname', 'ds_user_info.headimg')->paginate(CommentConfig::ChapterCommentPaginate);
        $fatherId = [];
        $pageId = isset($_GET['page']) ? $_GET['page'] : 1;
        foreach($commentInfo as $CI) {
            array_push($fatherId, $CI->id);
        }
        
        $commentLikeList = Cache::remember('chapter:comment:like:list:'.$chapterId.'_'.$pageId, 30 * 30, function() use($fatherId) {
            return DB::table('ds_comment_dianzan')->whereIn('comment_id', $fatherId)->select('id', 'user_id', 'comment_id')->get();
        });
        $commentReplyInfo = DB::table('ds_book_comment')->whereIn('ds_book_comment.pid', $fatherId)->orderBy('ds_book_comment.id', 'desc')->leftJoin('ds_user_info', 'ds_book_comment.userid', '=', 'ds_user_info.id')->select('ds_book_comment.*','ds_user_info.level', 'ds_user_info.nickname', 'ds_user_info.headimg')->get();
        $data = [
            'comment' => $commentInfo,
            'reply' => $commentReplyInfo
        ];
        return successJson($data);
    }
    public function authorChangeCommentState(Request $request)
    {
        $userInfo = $request->get('userInfo');
        $validrules = [
            'bookid' => 'required|numeric',
            'commentid' => 'required|numeric',
            'state' => 'required|boolean',
        ];
        $validmessages = [
            'bookid.required' => '书籍id必需',
            'commentid.required' => '评论id必需'
        ];
        validateParams($request->only('bookid', 'commentid'), $validrules, $validmessages);
        $book = DB::table('ds_book_info')->where('id', $request->input('bookid'))->first();
        if (!$book) {
            return errorJson('BOOK_NOT_FOUND');
        }
        if ($book->userid != $userInfo['id']) {
            return errorJson('DENY_ALLOW_MANAGE_THIS_BOOK');
        }
        $comment = DB::table('ds_book_comment')->where('id', $request->input('commentid'))->first();
        if (!$comment) {
            // 没有这个评论
            return errorJson('COMMENT_PARAM_MISSING');
        }
        if ($comment->bookid != $book->id) {
            // 如果评论的书籍ID 不等于传入的书籍ID
            return errorJson('COMMENT_PARAM_MISSING');
        }
        $state = $request->input('state') == true ? 2 : 1; // 2为恢复评论，1为删除评论，如果state为true，则表示为恢复，其他任何均为删除
        try {
            DB::beginTransaction();
            DB::table('ds_book_comment')->where('id', $comment->id)->update([
                'status' => $state,
            ]);
            if ($comment->pid == 0) {
                // 如果此评论是父评论，则需要一并标记子评论
                DB::table('ds_book_comment')->where('pid', $comment->id)->update([
                    'status' => $state,
                ]);
            }
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
        }
    }
    public function getMyComment(Request $request)
    {
        $userInfo = $request->get('userInfo');
        $validrules = [
            'role' => 'required|alpha',  
        ];
        $page = isset($_GET['page']) ? $_GET['page'] : 1;
        validateParams($request->only('role'), $validrules);
        switch ($request->input('role')) {
            case 'author':
                $book = DB::table('ds_book_info')->where('userid', $userInfo['id'])->pluck('id');
                $data = Cache::remember('author:comment:list:'.$userInfo['id'].'_'.$page, 3600,function()use($book) {
                    return DB::table('ds_book_comment')->whereIn('ds_book_comment.bookid', $book)->where('ds_book_comment.status', 2)->leftJoin('ds_book_info', 'ds_book_comment.bookid', '=', 'ds_book_info.id')->leftJoin('ds_book_comment as child_comment', 'ds_book_comment.pid', '=', 'child_comment.id')->leftJoin('ds_book_chapter', 'ds_book_comment.chapterid', '=', 'ds_book_chapter.id')->select('ds_book_comment.*', 'ds_book_info.bookname', 'ds_book_chapter.chaptername', 'child_comment.id as father_id', 'child_comment.nickname as father_nickname', 'child_comment.content as father_content', 'child_comment.logtime as father_logtime')->paginate(100);
                });
                if (Redis::get('user:comment:unread:'.$userInfo['id'])) {
                    DB::table('ds_unread_comment')->where('userid', $userInfo['id'])->delete();
                    Redis::del('user:comment:unread:'.$userInfo['id']);
                }
                $returnData = [
                    'data' => $data,
                    'can_delete' => 1,
                ];
                return successJson($returnData);
                break;
            case 'reader':
                $data = Cache::remember('reader:comment:list:'.$userInfo['id'].'_'.$page, 3600, function()use($userInfo) {
                    return DB::table('ds_book_comment')->where('ds_book_comment.userid', $userInfo['id'])->where('ds_book_comment.status', 2)->leftJoin('ds_book_info', 'ds_book_comment.bookid', '=', 'ds_book_info.id')->leftJoin('ds_book_comment as child_comment', 'ds_book_comment.pid', '=', 'child_comment.id')->leftJoin('ds_book_chapter', 'ds_book_comment.chapterid', '=', 'ds_book_chapter.id')->select('ds_book_comment.*', 'ds_book_info.bookname', 'ds_book_chapter.chaptername', 'child_comment.id as father_id', 'child_comment.nickname as father_nickname', 'child_comment.content as father_content', 'child_comment.logtime as father_logtime')->paginate(100);
                });
                $returnData = [
                    'data' => $data,
                    'can_delete' => 0,
                ];
                return successJson($returnData);
                break;
            default:
                return errorJson();
                break;
        }
    }
    public function likeComment(Request $request)
    {
        $userInfo = $request-get('userInfo');
        $validrules = [
            'commentid' => 'required|numeric'
        ];
        $commentId = $request->input('commentid');
        $likeInfo = DB::table('ds_comment_dianzan')->where('commentid', $commentId)->where('userid', $userInfo['id'])->first();
        if (!$likeInfo) {
            $data = [
                'userid' => $userInfo['id'],
                'commentid' => $commentId,
            ];
            likeComment::dispatch($data);
            return successJson();
        } else {
            return errorJson('YOU_HAVE_LIKED');
        }
    }
    
   
}
