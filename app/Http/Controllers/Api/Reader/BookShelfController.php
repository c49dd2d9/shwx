<?php

namespace App\Http\Controllers\Api\Reader;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class BookShelfController extends Controller
{
    public function create(Request $request)
    {
        $validrules = [
            'name' => 'required|max:10',
        ];
        validateParams($request->only('name'), $validrules);
        $userInfo = $request->get('userInfo');
        $level = $userInfo['level'];
        $userAuth = getLevelInfo($level);
        if ($userAuth == null) {
            return errorJson();
        }
        $userBookShelfCount = DB::select('EXPLAIN select * from ds_user_collect_bookshelf where userid='.$userInfo['id']);
        if ($userBookShelfCount[0].rows >= $userAuth->bookshelf_series_count) {
            return errorJson('BOOK_SHELF_SELF_COUNT_LIMIT');
        }
        DB::table('ds_user_collect_bookshelf')->insert([
            'userid' => $userInfo['id'],
            'name' => e($request->input('name')),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return successJson();
    }
    public function delete(Request $request, $id)
    {
        $userInfo = $request->get('userInfo');
        $userBookShelf = DB::table('ds_user_collect_bookshelf')->where('id', $id)->frist();
        if (!$userBookShelf || $userBookShelf->userid != $userInfo['id']) {
            return errorJson();
        }
        try {
            DB::beginTransaction();
            DB::table('ds_user_collect_bookshelf')->where('id', $userBookShelf->id)->delete();
            DB::table('ds_user_collect_book')->where('bookshelfid', $id)->update([
                'bookshelfid' => 0
            ]);
            DB::commit();
            return successJson();
        } catch (\Throwable $th) {
            DB::rollBack();
            return errorJson();
        }
    }
    public function getShelfList(Request $request)
    {
        $userInfo = $request->get('userInfo');
        $data = DB::table('ds_user_collect_bookshelf')->where('userid', $userInfo['id'])->select('id', 'name')->get();
        return $data;
    }
}
