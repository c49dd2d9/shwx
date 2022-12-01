<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\Book;
use App\Models\User;
use App\Http\Resources\AuthorBookResource;
use App\Enums\BookConfig;
use App\Jobs\GiveBookTicket;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

// 作品Controller
class BookController extends Controller
{
    /**
     * @router /create/book
     * @params { classid, bookname, bookintro, role, recomtext}
     * @response BaseResponse
     * @name 创建新书
     */
    public function create(Request $request)
    {
        $validrules = [
            'classid' => 'required|numeric',
            'bookname' => 'required|max:15',
            'bookintro' => 'required|min:10|max:500',
            'role' => 'required|max:100',
            'recomtext' => 'required|max:60',
            'book_tag' => 'required',
            'ycx' => 'required|numeric',
            'sj' => 'required|numeric',
            'sk' => 'required|numeric'
        ];
        $validmessages = [
            'recomtext.required' => '您必须输入一句话简介',
            'recomtext.max' => '一句话简介最大输入60个字哦~',
            'classid.required' => '您必须选择分类',
            'classid.numeric' => '分类格式不正确',
            'bookintro.required' => '简介必须填写',
            'bookintro.min' => '简介最少输入10个字哦~',
            'bookintro.max' => '简介最大输入500个字哦~',
            'bookname.required' => '作品名称必须填写',
            'bookname.max' => '作品名称不得大于15个字',
            'book_tag.required' => '设置TAG可以更好地帮助读者找文哟~'
        ];
        $userInfo = $request->get('userInfo');
        validateParams($request->only('classid', 'bookname', 'bookintro', 'role', 'recomtext', 'book_tag', 'ycx', 'sk', 'sj'), $validrules, $validmessages);
        if (!in_array($request->input('ycx'), [ 1, 2 ])) {
            return errorJson('BOOK_EXTEND_DENY_ALLOW');
        }
        if (!in_array($request->input('sk'), [ 1, 2, 3, 4 ])) {
            return errorJson('BOOK_EXTEND_DENY_ALLOW');
        }
        if (!in_array($request->input('sj'), [ 1, 2, 3, 4, 5 ])) {
            return errorJson('BOOK_EXTEND_DENY_ALLOW');
        }
        $classInfo = DB::table('ds_book_class')->where('id', $request->input('classid'))->first();
        if (!$classInfo) {
            return errorJson('CLASS_NOT_FOUND');
        }
        $categoryInfo = DB::table('ds_book_category')->where('id', $classInfo->categoryid)->first();
        if (!$categoryInfo) {
            return errorJson('CLASS_NOT_FOUND');
        }
        $bookTag = explode(',', $request->input('book_tag'));
        if (count($bookTag) > BookConfig::BookTagMaxCount) {
            return errorJson('TAG_DOES_NOT_MEET_THE_RULE');
        }
        $tagInfo = DB::table('ds_book_tag')->whereIn('tagname', $bookTag)->get();
        $tagInfoName = [];
        $tagIdList = [];
        foreach ($tagInfo as $TI) {
            array_push($tagInfoName, $tagInfo->tagname);
        }
        foreach ($bookTag as $BT) {
            if (!in_array($BT, $tagInfoName)) {
                $getId = DB::table('ds_book_tag')->insertGetId([
                    'tagname' => $BT,
                    'logtime' => date('Y-m-d H:i:s'),
                ]);
                if ($getId) {
                    array_push($tagIdList, $getId);
                }
            }
        }
        try {
            DB::beginTransaction();
            $book = new Book;
            $book->userid = $userInfo['id'];
            $book->classid = $classid;
            $book->unionbookid = 0;
            $book->bookimg = $request->input('bookimg');
            $book->bookname = e($request->input('bookname'));
            $book->writername = $userInfo['nickname'];
            $book->bookintro = e($request->input('bookintro'));
            $book->role = e($request->input('role'));
            $book->book_tag = e($request->input('book_tag'));
            $book->bookcnt = 0;
            $book->weekrecompiao = 0;
            $book->lastweekrecompiao = 0;
            $book->monthrecompiao = 0;
            $book->lastweekcuigeng = 0;
            $book->cuigengpiao = 0;
            $book->endstatus = 1;
            $book->publishstatus = 1;
            $book->publishtime = date('Y-m-d H:i:s', time());
            $book->category = $categoryInfo->categoryname;
            $book->class_name = $classInfo->classname;
            $book->save();
            $Tagdata = [];
            foreach ($tagIdList as $TID) {
                $data = [
                    'bookid' => $book->id, 
                    'tagid' => $TID,
                ];
                array_push($Tagdata, $data);
            }
            DB::table('ds_book_tag_data')->insert($Tagdata);
            $sectionData = [
                ['bookid' => $book->id, 'sectionname' => '作品相关', 'sortorder' => 1, 'logtime' => date('Y-m-d H:i:s', time())],
                ['bookid' => $book->id, 'sectionname' => '第一卷', 'sortorder' => 2, 'logtime' => date('Y-m-d H:i:s', time())]
            ];
            DB::table('ds_book_extend_data')->insert([
                'bookid' => $book->id,
                'extendcombox1' => $request->input('ycx'),
                'extendcombox2' => $request->input('sk'),
                'extendcombox3' => $request->input('sj'),
            ]);
            DB::table('ds_book_sections')->insert($sectionData);
            DB::commit();
            return successJson();
        } catch (\Throwable $th) {
            DB::rollBack();
            return errorJson();
        }
    }
    /**
     * @router /author/get/book
     * @params { id }
     * @response BaseResponse
     * @name 获取作品【作者端】
     */
    public function authorGetBookInfo(Request $request)
    {
        $id = $request->input('id') ? $request->input('id') : 0;
        if ($id == 0) {
            return errorJson('BOOK_NOT_FOUND');
        }
        $userInfo = $request->get('userInfo');
        $book = Book::find($id);
        if (!$book) {
            return errorJson('BOOK_NOT_FOUND');
        }
        if ($book->userid != $userInfo['id']) {
            return errorJson('DENY_ALLOW_MANAGE_THIS_BOOK');
        }
        if ($book->publishstatus == 4) {
            return errorJson('DENY_ALLOW_MANAGE_THIS_BOOK');
        }
        $tagIdList = DB::table('ds_book_tag_data')->where('bookid', $book->id)->pluck('tagid');
        $tagInfo = DB::table('ds_book_tag')->whereIn('id', $tagIdList)->get();
        $data = [
            'book' => AuthorBookResource($book),
            'tag' => $tagInfo
        ];
        return successJson($data);
    }
    /**
     * @router /update/book/info
     * @params { id, classid, bookname, bookimg, book_tag, bookintro, role, recomtext }
     * @response BaseResponse
     * @name 作者更新作品信息
     */
    public function update(Request $request)
    {
        $validrules = [
            'id' => 'required|numeric',
            'classid' => 'required|numeric',
            'bookname' => 'required|max:15',
            'bookimg' => 'required',
            'book_tag' => 'required',
            'bookintro' => 'required|min:10|max:500',
            'role' => 'required|max:100',
            'recomtext' => 'required|max:60',

        ];
        $validmessages = [
            'recomtext.required' => '您必须输入一句话简介',
            'recomtext.max' => '一句话简介最大输入60个字哦~',
            'classid.required' => '您必须选择分类',
            'classid.numeric' => '分类格式不正确',
            'bookintro.required' => '简介必须填写',
            'bookintro.min' => '简介最少输入10个字哦~',
            'bookintro.max' => '简介最大输入500个字哦~',
            'bookimg.required' => '您必须使用一个封面',
            'book_tag.required' => '输入标签有助于读者快速找书哦~',
            'bookname.required' => '作品名称必须填写',
            'bookname.max' => '作品名称不得大于15个字',
        ];
        $userInfo = $request->get('userInfo');
        validateParams($request->only('id', 'classid', 'bookname', 'bookimg', 'book_tag', 'bookintro', 'role', 'recomtext'), $validrules, $validmessages);
        $book = Book::find($request->input('id'));
        if (!$book) {
            return errorJson('BOOK_NOT_FOUND');
        }
        if ($book->userid != $userInfo['id']) {
            return errorJson('DENY_ALLOW_MANAGE_THIS_BOOK');
        }
        if ($book->publishstatus == 4) {
            return errorJson('DENY_ALLOW_MANAGE_THIS_BOOK');
        }
        if ($request->input('classid') != $book->classid) {
            $classInfo = DB::table('ds_book_class')->where('id', $request->input('classid'))->first();
            if (!$classInfo) {
                return errorJson('CLASS_NOT_FOUND');
            }
            $categoryInfo = DB::table('ds_book_category')->where('id', $classInfo->categoryid)->first();
            if (!$categoryInfo) {
                return errorJson('CLASS_NOT_FOUND');
            }  
        }
        $bookTag = explode(',', $request->input('book_tag'));
        if (count($bookTag) > BookConfig::BookTagMaxCount) {
            return errorJson('TAG_DOES_NOT_MEET_THE_RULE');
        }
        if ($book->issign == 1) {
            // 签约作品
            DB::table('ds_temp_book_info')->insert([
                'bookid' => $book->id,
                'bookimg' => $request->input('bookimg'),
                'bookname' => e($request->input('bookname')),
                'classid' => $request->input('classid'),
                'recomtext' => $request->input('recomtext'),
                'tag' => $request->input('book_tag'),
                'role' => $request->input('role'),
                'auditstatus' => 0
            ]);
        } else {
            // 普通作品
            if ($request->input('book_tag') != $book->book_tag) {
                $tagInfo = DB::table('ds_book_tag')->whereIn('tagname', $bookTag)->get();
                $tagInfoName = [];
                $tagIdList = [];
                foreach ($tagInfo as $TI) {
                    array_push($tagInfoName, $tagInfo->tagname);
                }
                foreach ($bookTag as $BT) {
                    if (!in_array($BT, $tagInfoName)) {
                        $getId = DB::table('ds_book_tag')->insertGetId([
                            'tagname' => $BT,
                            'logtime' => date('Y-m-d H:i:s'),
                        ]);
                        if ($getId) {
                            array_push($tagIdList, $getId);
                        }
                    }
                }
                DB::table('ds_book_tag_data')->where('bookid', $book->id)->delete();
                $addBookTagData = [];
                foreach ($tagIdList as $TID) {
                    $data = [
                       'bookid' => $book->id, 
                       'tagid' => $TID,
                    ];
                    array_push($addBookTagData, $data);
                }
                DB::table('ds_book_tag_data')->insert($addBookTagData);
            }
            $updateBookData = new Book;
            $updateBookData->bookname = e($request>input('bookname'));
            $updateBookData->bookimg = e($request->input('bookimg'));
            $updateBookData->id = $book->id;
            $updateBookData->book_tag =$request->input('book_tag');
            $updateBookData->bookintro = e($request->input('bookintro'));
            if ($request->input('classid') != $book->classid) {
                $updateBookData->classid = $request->input('classid');
                $updateBookData->category = $categoryInfo->categoryname;
                $updateBookData->class_name = $classInfo->classname;
            }
            $updateBookData->save();
        }
        return successJson();
    }
    /**
     * @router /create/section
     * @params { sectionname, bookid }
     * @response BaseResponse
     * @name 增加书卷
     */
    public function createSection(Request $request) {
        $validrules = [
            'sectionname' => 'required|max:15',
            'bookid' => 'required|numeric'
        ];
        $validmessages = [
            'sectioname.required' => '请输入卷名',
            'bookid.required' => '您必须选择要添加的作品',
            'bookid.numeric' => '作品选择失败',
            'sectionname.max' => '卷名不得大于 :max 字'
        ];
        $userInfo = $request->get('userInfo');
        validateParams($request->only('sectionname', 'bookid'), $validrules, $validmessages);
        $book = Book::find($request->input('bookid'));
        if (!$book) {
            return errorJson('BOOK_NOT_FOUND');
        }
        if ($book->userid != $userInfo['id']) {
            return errorJson('DENY_ALLOW_MANAGE_THIS_BOOK');
        }
        DB::table('ds_book_sections')->insert([
            'bookid' => $book->id,
            'sectionname' => e($request->input('sectionname')),
        ]);
        return successJson();
    }
    /**
     * @router /get/section/info
     * @params { bookid }
     * @response BaseResponse
     * @name 获取当前作品的分类卷
     */
    public function getSectionInfo(Request $request)
    {
        $bookId = $request->input('bookid') ? $request->input('bookid') : 0;
        if ($bookId == 0) {
            return errorJson('BOOK_NOT_FOUND');
        }
        $book = Book::find($bookId);
        if (!$book || $book->publishstatus == 4) {
            return errorJson('BOOK_NOT_FOUND');
        }
        if ($book->userid != $userInfo['id']) {
            return errorJson('DENY_ALLOW_MANAGE_THIS_BOOK');
        }
        $sectionList = DB::table('ds_book_sections')->where('bookid', $book->id)->get();
        return successJson($sectionList);
    }
    /**
     * @router /change/section
     * @params { sectionname, bookid, sectionid }
     * @response BaseResponse
     * @name 修改卷名
     */
    public function changeSection(Request $request)
    {
        $validrules = [
            'sectionname' => 'required|max:15',
            'bookid' => 'required|numeric',
            'sectionid' => 'required|numeric',
        ];
        $validmessages = [
            'sectioname.required' => '请输入卷名',
            'bookid.required' => '您必须选择要添加的作品',
            'bookid.numeric' => '作品选择失败',
            'sectionname.max' => '卷名不得大于 :max 字',
            'sectionid.required' => '您必须选择要修改的卷id'
        ];
        $userInfo = $request->get('userInfo');
        validateParams($request->only('sectionname', 'bookid', 'sectionid'), $validrules, $validmessages);
        $book = Book::find($request->input('bookid'));
        if (!$book || $book->publishstatus == 4) {
            return errorJson('BOOK_NOT_FOUND');
        }
        if ($book->userid != $userInfo['id']) {
            return errorJson('DENY_ALLOW_MANAGE_THIS_BOOK');
        }
        $sectionInfo = DB::table('ds_book_sections')->where('id', $request->input('sectionid'))->first();
        if (!$sectionInfo) {
            return errorJson('SECTION_NOT_FOUND');
        }
        if ($sectionInfo->bookid != $book->id) {
            return errorJson('SECTION_DATA_ERROR');
        }
        if ($sectionInfo->sectionname == '作品相关') {
            return errorJson('CANNOT_CHANGE_THIS_SECTION');
        }
        DB::table('ds_book_sections')->where('id', $sectionInfo->id)->update([
            'sectionname' => e($request->input('sectionname')),
        ]);
        return successJson();
    }
     /**
     * @router /delete/section
     * @params { sectionid }
     * @response BaseResponse
     * @name 删除作品卷
     */
    public function deleteSection(Request $request)
    {
        $validrules = [
            'sectionid' => 'required|numeric',
        ];
        $validmessages = [
            'sectionid.required' => '您必须选择要修改的卷id'
        ];
        $userInfo = $request->get('userInfo');
        validateParams($request->only('sectionid'), $validrules, $validmessages);
        $sectionInfo = DB::table('ds_book_sections')->where('id', $request->input('sectionid'))->first();
        if (!$sectionInfo) {
            return errorJson('SECTION_NOT_FOUND');
        }
        if ($sectionInfo->sectionname == '作品相关') {
            return errorJson('CANNOT_CHANGE_THIS_SECTION');
        }
        $book = Book::find($sectionInfo->bookid);
        if (!$book || $book->publishstatus == 4) {
            return errorJson('BOOK_NOT_FOUND');
        }
        if ($book->userid != $userInfo['id']) {
            return errorJson('DENY_ALLOW_MANAGE_THIS_BOOK');
        }
        $chapterCount = DB::table('ds_book_chapter')->where('sectionid', $sectionInfo->id)->count();
        if ($chapterCount > 0) {
            return errorJson('SECTION_HAS_CHAPTER');
        }
        try {
            DB::beginTransaction();
            DB::table('ds_book_chapter')->where('sectionid', $sectionInfo->id)->update([
                'sectionid' => 0,
            ]);
            DB::table('ds_book_sections')->where('id', $sectionInfo->id)->delete();
            DB::commit();
            return successJson();
        } catch (\Throwable $th) {
            DB::rollBack();
            return errorJson();
        }
    }
    public function userGiveawayBookTicket(Request $request)
    {
        $validrules = [
            'count' => 'required|numeric|min:1',
            'ticket_name' => 'required',
            'bookid' => 'required|numeric'
        ];
        $validmessages = [
            'count.required' => '赠送数量必须',
            'count.numeric' => '赠送数量不合法',
            'ticket_name.required' => '赠送的票必须',
            'bookid.required' => '赠送对象必须',
            'bookid.numeric' => '赠送对象不合法'
        ];
        validateParams($request->only('count', 'ticket_name', 'bookid'), $validrules, $validmessages);
        $userInfo = $request->get('userInfo');
        $user = User::find($userInfo['id']);
        $ticketName = $request->input('ticket_name');
        if (!$user || $user->islock == 1) {
            return errorJson('USER_CANNOT_FOUND');
        }
        switch ($ticketName) {
            case 'yuepiao':
                $ticketCount = $user->yuepiao;
                $dbFieldName = 'yuepiao';
                break;
            case 'tuijianpiao':
                $ticketCount = $user->recompiao;
                $ticketType = 2;
                $dbFieldName = 'recompiao';
                break;
            case 'cuigengpiao':
                $ticketCount = $user->cuigengpiao;
                $ticketType = 1;
                $dbFieldName = 'cuigengpiao';
                break;
            default:
                return errorJson();
                break;
        }
        
        if ($ticketCount < $request->input('count')) {
            return errorJson('TICKET_COUNT_INSUFFICIENT');
        }
        $book = Book::find($request->input('bookid'));
        if (!$book || $book->publishstatus != BookConfig::BookNormalPublishstatus) {
            return errorJson('BOOK_NOT_FOUND');
        }
        $data = [
            'db_filed_name' => $dbFieldName,
            'count' => $count,
            'ticket_type' => isset($ticketType) ? $ticketType : 0,
            'bookid' => $book->id,
            'userid' => $user->id,
        ];
        GiveBookTicket::dispatch($data);
        return successJson();
    }
    /**
     * @router /book/update/calendar/{bookid}
     * @params {}
     * @response BaseResponse
     * @name 获取当前作品某年某月的更新状态
     */
    public function updateCalendar($bookid) {
        $year = isset($_GET['year']) ? $_GET['year'] : '';
        $month = isset($_GET['month']) ? $_GET['month'] : '';
        if ($year == '' || $month == '') {
            return errorJson();
        }
        $updateInfo = DB::table('ds_book_update_info')->where('bookid', $bookid)->where('year', $year)->where('month', $month)->select('daily', 'wordcnt')->get();
        return successJson($updateInfo);
    }
}
