<?php

namespace App\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use App\Enums\BanConfig;
use App\Exceptions\AdminApiException;
use App\Http\Controllers\Controller;

class CommentController extends Controller
{
    public function getBookComment(Request $request)
    {
        $validrules = [
            'bookid' => 'numeric',
            'status' => 'required|numeric',
        ];
        validateParams($request->all(), $validrules);
        $bookId = $request->input('bookid') ? $request->input('bookid') : 0;
        $status = $request->input('status');
        if (!in_array($status, [ 1, 2])) {
            throw new AdminApiException('COMMENT_SEARCH_MODULE_ID_DENY_ALLOW');
        }
        if ($bookId <= 0) {
            $commentList = DB::table('ds_book_comment')->where('status', $status)->paginate(100);
        } else {
            $commentList = DB::table('ds_book_comment')->where('bookid', $bookId)->where('status', $status)->paginate(100);
        }
        return successJson($commentList);
    }
    public function delete($comment_id)
    {
        $commentInfo = DB::table('ds_book_comment')->where('id', $comment_id)->first();
        if (!$commentInfo || $commentInfo->status == 1) {
            throw new AdminApiException('COMMENT_HAS_BEEN_DELETED');
        }
        DB::table('ds_book_comment')->where('id', $commentInfo->id)->update([
            'status' => 2,
        ]);
        return successJson();
    }
    public function restore($comment_id)
    {
        $commentInfo = DB::table('ds_book_comment')->where('id', $comment_id)->first();
        if (!$commentInfo) {
            throw new AdminApiException('COMMENT_HAS_BEEN_DELETED');
        }
        if ($commentInfo->status == 2) {
            throw new AdminApiException('COMMENT_STATUS_NORMAL');
        }
        DB::table('ds_book_comment')->where('id', $commentInfo->id)->update([
            'status' => 1,
        ]);
        return successJson();
    }
    public function banUserCommentAuth(Request $request)
    {
        $validrules = [
            'commentid' => 'required|numeric',
            'expired_time' => 'required|numeric',
            'reason' => 'required|min:5|max:30',
        ];
        validateParams($request->all(), $validrules);
        $commentInfo = DB::table('ds_book_comment')->where('id', $comment_id)->first();
        if (!$commentInfo) {
            throw new AdminApiException('COMMENT_HAS_BEEN_DELETED');  
        }
        checkBanExpiredTimeInAllowList($request->input('expired_time'));
        $user = User::find($commentInfo->userid);
        if (!$user || $user->islock) {
            throw new AdminApiException('THIS_ACCOUNT_IS_LOCKED');
        }
        $userBanInfo = DB::table('ds_ban_user')->where('userid', $user->id)->where('moduleid', BanConfig::BanComment)->first();
        if ($userBanInfo) {
            $newBanTime = $userBanInfo->expired_time + time() + $request->input('expired_time');
            DB::table('ds_ban_user')->where('id', $userBanInfo->id)->update([
                'expired_time' => $newBanTime,
                'reason' => $request->input('reason'),
            ]);
        } else {
            DB::table('ds_ban_user')->insert([
                'userid' => $user->id,
                'nickname' => $user->nickname,
                'expired_time' => time() + $request->input('expired_time'),
                'reason' => $request->input('reason'),
                'moduleid' => BanConfig::BanComment,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        return successJson();
    }
    public function getBanList()
    {
        $list = Cache::remember('ban:datalist', 1800, function() {
            return DB::table('ds_ban_list')->where('moduleid', BanConfig::BanComment)->get();
        });
        return successJson($list);
    }
    public function deleteBanUserRecord($id)
    {
        $banInfo = DB::table('ds_ban_user')->where('id', $id)->first();
        if ($banInfo) {
            DB::table('ds_ban_user')->where('id', $id)->delete();
        } else {
            throw new AdminApiException('DEFAULT_ERROR');
        }
    }
}

