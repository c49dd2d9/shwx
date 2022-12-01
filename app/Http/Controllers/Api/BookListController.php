<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Arr;
use App\Http\Controllers\Controller;

// 书单Controller
class BookListController extends Controller
{
    public function create(Request $request)
    {
        $validrules = [
            'name' => 'required|min:3|max:12',
            'intro' => 'required|min:3|max:1000',
        ];
        $validmessage = [
            'name.required' => '您必须输入书单名称',
            'name.min' => '书单名不得小于 :min 字',
            'name.max' => '书单名不得大于 :max 字',
            'intro.required' => '您必须为您的书单输入一段介绍（3-1000字）',
            'intro.min' => '书单介绍不得小于 :min 字',
            'intro.max' => '书单介绍不得大于 :max 字'
        ];
        $userInfo = $request->get('userInfo');
        validateParams($request->only('name', 'intro'), $validrules, $validmessage);
        $userLevel = getLevelInfo($userInfo['level']);
        if ($userLevel->book_list_count == 0) {
            return errorJson('USER_NOT_AUTH_CREATE_BOOK_LIST');
        }
        $userCreateBookListCount = DB::table('ds_book_list_info')->where('userid', $userInfo['id'])->count();
        if ($userLevel->book_list_count <= $userCreateBookListCount) {
            return errorJson('USER_NOT_AUTH_CREATE_BOOK_LIST');
        }
        DB::table('ds_book_list_info')->insert([
            'userid' => $userInfo['id'],
            'name' => e($request->input('name')),
            'intro' => e($request->input('intro')),
            'nickname' => $userInfo['nickname'],
            'focus_num' => 0,
            'comment_num' => 0,
            'new_book_img' => '',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return successJson();
    }
    public function getMyBookList(Request $request)
    {
        $userInfo = $request->get('userInfo');
        $bookListData = DB::table('ds_book_list_info')->where('userid', $userInfo['id'])->get();
        return successJson($bookListData);
    }
    public function addBookToBookList(Request $request)
    {
        $userInfo = $request->get('userInfo');
        $validrules = [
            'booklistid' => 'required|numeric',
            'book_id_list' => 'required'
        ];
        $validmessage = [
            'booklistid.required' => '您必须选择书单',
            'booklistid.numeric' => '参数传入不符合规则',
            'book_id_list.required' => '添加失败，没有添加书籍',
        ];
        validateParams($request->only('booklistid', 'book_id_list'), $validrules);
        $bookIdList = $request->input('book_id_list');
        if (!is_array($bookIdList)) {
            return errorJson('BOOK_ID_LIST_NOT_ARRAY');
        }
        $newBookIdListCount = count($bookIdList);
        if ($newBookIdListCount > 10) {
            return errorJson('A_REQUEST_JUST_ADD_TEN_BOOK');
        }
        $bookListInfo = DB::table('ds_book_list_info')->where('id', $request->input('booklistid'))->first();
        if (!$bookListInfo) {
            return errorJson('BOOK_LIST_DATA_NOT_FOUND');
        }
        if ($bookListInfo->userid != $userInfo['id']) {
            return errorJson('DENY_ALLOW_MANAGE_BOOK_LIST');
        }
        $bookListData = DB::table('ds_book_list_data')->where('booklistid', $bookListInfo->id)->pluck('bookid');
        if (count($bookListData) >= 100) {
            return errorJson('BOOK_LIST_DATA_MAX_LIMIT');
        }    
        if (count($bookListData) + $newBookIdListCount > 100) {
            return errorJson('BOOK_LIST_DATA_MAX_LIMIT');
        } 
        foreach ($bookIdList as $bookIdListItem) {
            if (in_array($bookIdListItem, $bookListData)) {
                unset($bookIdListItem);
            }
        }
        $bookvalidity = DB::table('ds_book_info')->whereIn('id', $bookIdList)->where('publishstatus', 1)->pluck('id');
        $data = [];
        foreach ($bookvalidity as $bookItem) {
           $newData = [
            'booklistid' => $bookListInfo->id,
            'bookid' => $bookItem,
            'created_at' => now(),
            'updated_at' => now()
           ];
           array_push($data, $newData);
        }
        if (count($bookvalidity) > 4) {
            $newFourBookIdList = array_slice($bookvalidity, 0, 4);
        } else {
            $newFourBookIdList = array_slice($bookvalidity, 0, count($bookvalidity));
        }
        $bookImg = DB::table('ds_book_info')->whereIn('id', $newFourBookIdList)->pluck('bookimg')->toArray();
        try {
            DB::beginTransaction();
            DB::table('ds_book_list_data')->insert($data);
            DB::table('ds_book_list_info')->where('id', $bookListInfo->id)->update([
                'new_book_img' => implode(',', $bookImg),
            ]);
            DB::commit();
            return successJson();
        } catch (\Throwable $th) {
            DB::rollBack();
            return errorJson();
        }
    }
    public function deleteBookList(Request $request, $booklistid)
    {
        $userInfo = $request->get('userInfo');
        $bookListData = DB::table('ds_book_list_info')->where('id', $booklistid)->first();
        if (!$bookListData) {
            return successJson();
        }
        if ($bookListData->userid != $userInfo['id']) {
            return errorJson('DENY_ALLOW_MANAGE_BOOK_LIST');
        }
        try {
            DB::beginTransaction();
            DB::table('ds_book_list_info')->where('id', $bookListData->id)->delete();
            DB::table('ds_book_list_data')->where('booklistid', $bookListData->id)->delete();
            DB::commit();
            return successJson();
        } catch (\Throwable $th) {
           DB::rollBack();
           return errorJson();
        }
    }
    public function deleteBookToBookList(Request $request)
    {
        $userInfo = $request->get('userInfo');
        $validrules = [
            'booklistid' => 'required|numeric',
            'id_list' => 'required'
        ];
        $validmessage = [
            'booklistid.required' => '您必须选择书单',
            'booklistid.numeric' => '参数传入不符合规则',
            'id_list.required' => '删除失败，请先选择您想要删除的书籍',
        ];
        validateParams($request->only('booklistid', 'id_list'), $validrules);
        $bookIdList = $request->input('id_list');
        if (!is_array($bookIdList)) {
            return errorJson('BOOK_ID_LIST_NOT_ARRAY');
        } 
        $bookListInfo = DB::table('ds_book_list_info')->where('booklistid', $request->input('booklistid'))->first();
        if (!$bookListInfo) {
            return errorJson('BOOK_LIST_DATA_NOT_FOUND');
        }
        if ($bookListInfo->userid != $userInfo['id']) {
            return errorJson('DENY_ALLOW_MANAGE_BOOK_LIST');
        }
        $bookListData = DB::table('ds_book_list_data')->whereIn('id', $bookIdList)->get();
        $deleteData = [];
        foreach ($bookListData as $bookItem) {
            if ($bookItem->booklistid == $bookListInfo->id) {
                array_push($deleteData, $bookItem->id);
            }
        }
        DB::table('ds_book_list_data')->whereIn('id', $deleteData)->delete();
        return successJson();
    }
    public function getBookListInfo($booklistid)
    {
        $bookList = DB::table('ds_book_list_info')->where('id', $booklistid)->first();
        if (!$bookList) {
            return errorJson('BOOK_LIST_DATA_NOT_FOUND');
        }
        $bookListBookData = Cache::remember('booklist:data:'.$bookList->id, 1800, function()use($bookList) {
            return DB::table('ds_book_list_data')->where('booklistid', $bookList->id)->pluck('bookid');
        });
        $bookInfo = Cache::remember('booklist:data:book:'.$bookList->id, 1800, function()use($bookListBookData) {
            return DB::table('ds_book_info')->whereIn('id', $bookListBookData)->select('id', 'bookname', 'bookintro', 'userid', 'writername', 'bookimg', 'publishstatus')->get();
        });
        foreach ($bookInfo as $bookItem) {
            if ($bookItem->publishstatus != 1) {
                unset($bookItem);
            }
        }
        $data = [
            'book_list_info' => $bookList,
            'book_list' => $bookInfo,
        ];
        return successJson($data);
    }
    public function createFocusOnBooklist(Request $request, $id)
    {
        $userInfo = $request->get('userInfo');
        $bookListInfo = DB::table('ds_book_list_info')->where('id', $id)->first();
        if (!$bookListInfo) {
            return errorJson('BOOK_LIST_DATA_NOT_FOUND');
        }
        $bookListFocusRecord = DB::table('ds_book_list_focus_data')->where('userid', $userInfo['id'])->pluck('booklistid');
        if (in_array($id, $bookListFocusRecord)) {
            return errorJson('REPEAT_FOCUS_ON_BOOK_LIST');
        }
        try {
            DB::beginTransaction();
            DB::table('ds_book_list_focus_data')->insert([
                'booklistid' => $bookListInfo->id,
                'userid' => $userInfo['id'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('ds_book_list_info')->where('id', $bookListInfo->id)->increment('focus_num');
            DB::commit();
            return successJson();
        } catch (\Throwable $th) {
            DB::rollBack();
            return errorJson();
        }
    }
    public function deleteFocusOnBookList(Request $request, $id) {
        $userInfo = $request->get('userInfo');
        $bookListFocusRecord = DB::table('ds_book_list_focus_data')->where('booklistid', $id)->where('userid', $userInfo['id'])->first();
        if (!$bookListFocusRecord) {
            return successJson();
        }
        try {
            DB::beginTransaction();
            DB::table('ds_book_list_info')->where('id', $bookListInfo->id)->decrement('focus_num');
            DB::table('ds_book_list_focus_data')->where('id', $bookListFocusRecord->id)->delete();
            DB::commit();
            return successJson();
        } catch (\Throwable $th) {
            return errorJson();
        }
    }
    public function getFocusOnList(Request $request)
    {
        $userInfo = $request->get('userInfo');
        $focusOnList = DB::table('ds_book_list_focus_data')->where('userid', $userInfo['id'])->pluck('booklistid');
        $bookListData = DB::table('ds_book_list_info')->whereIn('id', $focusOnList)->pageinate(50);
        return successJson($bookListData);
    }
}
