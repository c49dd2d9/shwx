<?php

namespace App\Http\Controllers\Api\Reader;

use Illuminate\Http\Request;
use App\Models\Book;
use App\Jobs\UserOperateCollect;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\Controller;

class BookController extends Controller
{
    public function createCollect(Request $request)
    {
        $validrules = [
            'bookid' => 'required|numeric',
            'bookshelfid' => 'numeric'
        ];
        validateParams($request->only('bookid'), $validrules);
        $userInfo = $request->get('userInfo');
        if ($request->input('bookshelfid') && $request->input('bookshelfid') != 0) {
            $bookShelfInfo = DB::table('ds_user_collect_bookshelf')->where('id', $request->input('bookshelfid'))->first();
            if (!$bookShelfInfo || $bookShelfInfo->userid  != $userInfo['id']) {
                return errorJson();
            }
            $bookShelfId = $bookShelfInfo->id;
        } else {
            $bookShelfId = 0;
        }
        $userLevelInfo = getLevelInfo($userInfo['level']);
        $userBookCollectCount = DB::raw('EXPLAIN select * from ds_user_collect_book where userid='.$userInfo['id']);
        if ($userLevelInfo->bookshelf_count <= $userBookCollectCount[0].rows) {
            return errorJson();
        }
        $book = Book::find($request->input('bookid'));
        if (!$book || $book->publishstatus == 1) {
            return errorJson();
        }
        $collect = DB::table('ds_user_collect_book')->where('userid', $userInfo['id'])->where('bookid', $book->id)->first();
        if ($collect) {
            return errorJson();
        }
        $data = [
            'userid' => $userInfo['id'],
            'bookid' => $book->id,
            'bookshelfid' => $bookShelfId,
            'iscollect' => true
        ];
        UserOperateCollect::dispatch($data);
        return successJson();
    }
    public function deleteCollect(Request $request)
    {
        $validrules = [
            'id_list' => 'required|numeric',
        ];
        validateParams($request->only('id_list'), $validrules);
        if (!is_array($request->input('id_list'))) {
            return errorJson();
        }
        $userInfo = $request->get('userInfo');
        $CollectList = DB::table('ds_user_collect_book')->whereIn('id', $request->input('id_list'))->where('userid', $userInfo['id'])->select('id', 'bookid');
        if (count($CollectList) <= 0 && count($CollectList) > 10) {
            return errorJson();
        }
        foreach ($CollectList as $item) {
            $data = [
                'id' => $item->id,
                'bookid' => $item->bookid,
                'iscollect' => false,
            ];
            UserOperateCollect::dispatch($data);
        }
        return successJson();
    }
    public function moveBookCollect(Request $request)
    {
        $validrules = [
            'id' => 'required|numeric',
            'mv_shelf_id' => 'required|numeric',
        ];
        validateParams($request->only('id', 'mv_shelf_id'), $validrules);
        $userInfo = $request->get('userInfo');
        $newBookShelfInfo = DB::table('ds_user_collect_bookshelf')->where('id', $request->input('mv_shelf_id'))->first();
        if (!$newBookShelfInfo || $newBookShelfInfo->userid != $userInfo['id']) {
            return errorJson();
        }
        $collectInfo = DB::table('ds_user_collect_book')->where('id', $request->input('id'))->first();
        if (!$collectInfo || $collectInfo->userid != $userInfo['id']) {
            return errorJson();
        }
        DB::table('ds_user_coolect_book')->where('id', $collectInfo->id)->update([
            'bookshelfid' => $newBookShelfInfo->id,
        ]);
        return succesJson();
    }
    public function getCollectList(Request $request)
    {
        $userInfo = $request->get('userInfo');
        $page = isset($_GET['page']) ? $_GET['page'] : 1;
        $pageLimit = 30;
        $pageOffset = ($page * $pageLimit) - 1;
        $bookShelfData = Cache::remember('user:bookshlef:data_'.$userInfo['id'], 3600 * 2, function()use($userInfo) {
            return DB::table('ds_user_collect_bookshelf')->where('userid', $userInfo['id'])->select('id', 'name')->get();
        });
        $collectData = Cache::remember('user:collect:data_'.$userInfo['id'].'_'.$page, 3600 * 2, function()use($userInfo, $pageLimit, $pageOffset) {
            return DB::table('ds_user_collect_book')->where('ds_user_collect_book.userid', $userInfo['id'])->limit($pageLimit)->offset($pageOffset)->orderBy('id', 'desc')->leftJoin('ds_book_info', 'ds_user_collect_book.bookid', '=', 'ds_book_info.id')->select('ds_user_collect_book.id', 'ds_user_collect_book.bookid', 'ds_user_collect_book.bookshelfid')->get();
        });
        $data = [
            'shelf' => $bookShelfData,
            'collect_list' => $collectData,
            'max_page_limit' => $pageLimit,
            'count' => count($collectData),
        ];
        return successJson($data);
    }
}
