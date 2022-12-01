<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Enums\ChapterConfig;
use App\Enums\BookConfig;
use App\Jobs\DaliyBookUpdate;
use App\Jobs\UpdateBookShelfUpdateInfo;
use App\Jobs\ArchivePublishChapter;
use Illuminate\Support\Facades\Cache;
use App\Models\Book;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Models\User;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

// 章节Controller
class ChapterController extends Controller
{
    public function create(Request $request)
    {
        $validrules = [
            'title' => 'required|max:10',
            'content' => 'required|min:10',
            'sectionid' => 'numeric',
            'publish_type' => 'required',
            'bookid' => 'required|numeric'
        ];
        $validmessages = [
            'title.required' => '标题是必须的哦~',
            'title.max' => '标题不能超过10个字',
            'content.required' => '内容是必须的哦~',
            'content.min' => '内容不能少于10个字',
            'sectionid.numeric' => '您选择的卷不合法',
            'bookid.required' => '您必须为您的章节选择一本书籍',
            'bookid.numeric' => '书籍不合法',
            'publish_type.required' => '您必须选择一个发布状态' 
        ];
        $userInfo = $request->get('userInfo');
        validateParams($request->only('title', 'content', 'sectionid', 'bookid', 'publish_type'), $validrules, $validmessages);
        $book = Book::find($request->input('bookid'));
        if (!$book || $book->publishstatus == 4) {
            return errorJson('BOOK_NOT_FOUND');
        }
        if ($book->userid != $userInfo['id']) {
            return errorJson('DENY_ALLOW_MANAGE_THIS_BOOK');
        }
        $sectionInfo = DB::table('ds_book_sections')->where('id', $request->input('sectionid'))->first();
        if (!$sectionInfo || $sectionInfo->bookid != $book->id) {
            return errorJson('SECTION_DATA_ERROR');
        }
        $chapterContentLength = comment_count_word($request->input('content'));
        $bookVipStatus = $book->isvip ? 1 : 0;
        if ($bookVipStatus == 1 && $chapterContentLength < 1000) {
            $bookVipStatus = 0;
        }
        $preChapterId = DB::table('ds_book_chapter')->where('bookid', $book->id)->max('id');
        $preChapterInfo = DB::table('ds_book_chapter')->where('id', $preChapterId)->first();
        try {
            DB::beginTransaction();
            $newChapterId = DB::table('ds_book_chapter')->insertGetId([
                'chaptertype' => 0,
                'sectionid' => $request->input('sectionid'),
                'bookid' => $book->id,
                'unionbookid' => 0,
                'unionchapterid' => 0,
                'chaptername' => e($request->input('title')),
                'chaptercontent' => e($request->input('content')),
                'chapterdesc' => $request->input('chapterdesc'),
                'wordcnt' => $chapterContentLength,
                'isvip' => $bookVipStatus,
                'status' => 1, //原本是定义是否审核的，新站改成是否被删除
                'istimepublish' => 0,
                'haspublished' => $request->input('publish_type') == 1 ? 1 : 0,
                'istemp' => $request->input('publish_type') == 1 ? 0 : 1,
                'publishtime' => $request->input('publish_type') == 1 ? date('Y-m-d H:i:s', time()) : '0000-00-00 00:00:00',
                'prechapterid' => $preChapterInfo->id,
                'prechaptervip' =>  $preChapterInfo->isvip,
                'updated_at' => now(),
            ]);
            DB::table('ds_book_chapter')->where('id', $preChapterInfo->id)->update([
                'nextchapterid' => $newChapterId,
                'nextchaptervip' => $bookVipStatus
            ]);
            if ($request->input('publish_type') == ChapterConfig::DirectPublishStatus) {
                // 如果是直接发布
                DB::table('ds_book_info')->where('id', $book->id)->increment('bookcnt', $chapterContentLength, ['lastchapterid' => $newChapterId, 'lastchapterupdatetime' => date('Y-m-d H:i:s', time())]);
                DB::table('ds_book_info')->where('id', $book->id)->increment('chaptercnt', 1);
                DB::table('ds_daliy_update_book')->insert([
                    'bookid' => $book->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                UpdateBookShelfUpdateInfo::dispatch([
                    'bookname' => $book->bookname,
                    'updatetime' => time(),
                    'bookid' => $book->id
                ]);
            }
            DB::commit();
            Cache::forget('qudao_book:'.$book->id);
            return successJson();
        } catch (\Throwable $th) {
            DB::rollBack();
            return errorJson();
        }
    }
    public function directPublishChapter(Request $reuqest)
    {
        $validrules = [
            'chapterid' => 'required',
            'bookid' => 'required',
        ];
        $userInfo = $request->get('userInfo');
        validateParams($request->only('chapterid', 'bookid'), $validrules);
        $book = Book::find($request->input('bookid'));
        if (!$book || $book->publishstatus != 1) {
            return errorJson();
        }
        if ($book->user_id != $userInfo['id']) {
            return errorJson('DENY_ALLOW_MANAGE_THIS_BOOK');
        }
        $chapterInfo = DB::table('ds_book_chapter')->where('id', $request->input('chapterid'))->first();
        if (!$chapterInfo) {
            return errorJson();
        }
        if ($chapterInfo->bookid != $book->id) {
            return errorJson();
        }
        if ($chapterInfo->istemp != 1) {
            return errorJson();
        } 
        try {
            DB::beginTransaction();
            if ($chapterInfo->istimepublish == 1) {
                DB::table('ds_chapter_job')->where('chapterid', $chapterInfo->id)->delete();
            }
            DB::table('ds_book_chapter')->where('id', $chapterInfo->id)->update([
                'istemp' => 0,
                'publishtime' => date('Y-m-d H:i:s'),
                'haspublished' => 1,
                'istimepublish' => 0,
            ]);
            DB::table('ds_book_info')->where('id', $book->id)->increment('bookcnt', $chapterInfo->wordcnt, ['lastchapterid' => $chapterInfo->id, 'lastchapterupdatetime' => date('Y-m-d H:i:s')]);
            DB::table('ds_book_info')->where('id', $book->id)->increment('chaptercnt', 1);
            DB::table('ds_daliy_update_book')->insert([
                'bookid' => $book->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::commit();
            return successJson();
        } catch (\Throwable $th) {
            DB::rollBack();
            return errorJson();
        }
    }
    public function changeArchiveChapter(Request $request)
    {
        $validrules = [
            'chapterid' => 'required',
            'bookid' => 'required',
            'publishtime' => 'required',
        ];
        $userInfo = $request->get('userInfo');
        validateParams($request->only('chapterid', 'publishtime', 'bookid'), $validrules);
        if (!is_numeric($request->input('bookid'))) {
            return errorJson();
        }
        $book = Book::find($request->input('bookid'));
        if (!$book || $book->userid != $userInfo['id']) {
            return errorJson('DENY_ALLOW_MANAGE_THIS_BOOK');
        }
        $chapterIdList = DB::table('ds_book_chapter')->where('bookid', $request->input('bookid'))->pluck('id');
        $data = $request->input('chapterid');
        $newData = [];
        $pubslishtime = $request->input('publishtime');
        foreach($data as $chapterListKey) {
            if (in_array($chapterListKey, $chapterIdList)) {
                array_push($newData, $chapterListKey);
            }
        }
        unset($chapterIdList);
        $chapterInfoList = DB::table('ds_book_chapter')->whereIn('id', $newData)->select('id', 'bookid', 'wordcnt', 'status')->get();
        $publishTimestamp = strtotime($request->input('publishtime'));
        if (time() > $publishTimestamp) {
            return errorJson('PUBLISHTIME_FAILED_MIN');
        }
        $timePublishData = [];
        foreach($chapterInfoList as $chapterInfoListKey) {
            if ($chapterInfoList->status != 1) {
                $newTimePublishData = [
                    'chapterid' => $chapterInfoListKey->id,
                    'bookid' => $book->id,
                    'wordcnt' => $chapterInfoListKey->wordcnt,
                    'publishtime' => $pubslishtime[$chapterInfoListKey->id],
                    'state' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            array_push($timePublishData, $newTimePublishData);
        }
        DB::table('ds_chapter_job')->insert($timePublishData);
        foreach($timePublishData as $timePublishDataKey) {
            DB::table('ds_book_chapter')->where('id', $timePublishDataKey['chapterid'])->update([
                'istimepublish' => 1,
                'publishtime' => $timePublishDataKey['publishtime']
            ]);
        }
        return successJson();
    }
    public function accountingBookUpdate()
    {
        // 更新当日作品更新情况
        $getBook = DB::table('ds_daliy_update_book')->whereDate('created_at', date('Y-m-d', strtotime("-1 days")))->select('bookid')->distinct()->get();
        foreach ($getBook as $bookKey) {
            $data = [
                'bookid' => $bookKey['bookid'],
            ];
            DaliyBookUpdate::dispatch($data);
        }
    }
    public function timePublishChapterQueue()
    {
        $getIsPublishChapter = DB::table('ds_chapter_job')->where('state', 0)->get();
        $timeLeft = time() - strtotime($chapterKey->publishtime);
        $timeLeftMinute = $timeLeft / 60;
        foreach($getIsPublishChapter as $chapterKey) {
            if ($timeLeft <= 5 * 60) {
                $data = [
                    'chaptetid' => $chapterKey->chapterid,
                    'bookid' => $chapterKey->bookid,
                    'wordcnt' => $chapterKey->wordcnt,
                    'jobid' => $chapterKey->id,
                    'publishtime' => $chapterKey->publishtime
                ];
                ArchivePublishChapter::dispatch($data)->delay(now()->addMinute($timeLeftMinute));
            }
        }
    }
    public function authorGetChapterList(Request $request)
    {
        $userInfo = $request->get('userInfo');
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
        $chapterList = DB::table('ds_book_chapter')->where('bookid', $book->id)->select('id', 'chaptertype', 'sectionid', 'bookid', 'chaptername', 'wordcnt', 'isvip', 'status', 'istimepublish', 'publishtime', 'istemp', 'logtime')->get();
        return successJson($chatperList);
    }
    public function authorGetChapterInfo(Request $request)
    {
        $validrules = [
            'chapterid' => 'required',
            'bookid' => 'required',
        ];
        $userInfo = $request->get('userInfo');
        validateParams($request->only('chapterid', 'bookid'), $validrules);
        $book = Book::find($request->input('bookid'));
        if ($book || $book->publishstatus == 4) {
            return errorJson('BOOK_NOT_FOUND'); 
        }
        if ($book->userid != $userInfo['id']) {
            return errorJson('DENY_ALLOW_MANAGE_THIS_BOOK');
        }
        $chapter = DB::table('ds_book_chapter')->where('id', $request->input('chapterid'))->select('id', 'chaptertype', 'sectionid', 'bookid', 'status', 'chaptername', 'chapterdesc', 'chaptercontent')->first();
        if (!$chapter) {
            return errorJson('CHAPTER_NOT_FOUND');
        }
        if ($chapter->status == 2) {
            return errorJson('CHAPTER_NOT_FOUND');
        }
        if ($chapter->bookid != $book->id) {
            return errorJson('DENY_ALLOW_MANAGE_THIS_CHAPTER');
        }
        return successJson($chapter);
    }
    public function updateChapterInfo(Request $request)
    {
        // 编辑章节信息不更新当日更新总字数
       $userInfo = $request->get('userInfo');
       $validrules = [
            'title' => 'required|max:10',
            'content' => 'required|min:10',
            'sectionid' => 'numeric',
            'bookid' => 'required|numeric',
            'chapterid' => 'required|numeric',
        ];
        $validmessages = [
            'title.required' => '标题是必须的哦~',
            'title.max' => '标题不能超过10个字',
            'content.required' => '内容是必须的哦~',
            'content.min' => '内容不能少于10个字',
            'sectionid.numeric' => '您选择的卷不合法',
            'bookid.required' => '您必须为您的章节选择一本书籍',
            'bookid.numeric' => '书籍不合法',
            'chapterid.numeric' => '章节ID不合法',
            'chapterid.required' => '章节ID缺失'
        ];
        validateParams($request->only('title', 'content', 'sectionid', 'bookid', 'chapterid'), $validrules, $validmessages);
        $book = Book::find($request->input('bookid'));
        if (!$book || $book->userid != $userInfo['id']) {
            return errorJson('DENY_ALLOW_MANAGE_THIS_BOOK');
        }
        $chapter = DB::table('ds_book_chapter')->where('id', $request->input('chapterid'))->first();
        if (!$chapter || $chapter->bookid != $book->id) {
            return errorJson('DENY_ALLOW_MANAGE_THIS_CHAPTER');
        }
        if ($chapter->sectionid != $request->input('sectionid')) {
            $sectionInfo = DB::table('ds_book_sections')->where('id', $request-input('sectionid'))->first();
            if (!$sectionInfo || $sectionInfo->bookid) {
                return errorJson('SECTION_DATA_ERROR');
            }
        }
        $chapterContentLength = comment_count_word($request->input('content'));
        $contentLengthGap = $chapter->wordcnt - $chapterContentLength;
        $preChapterId = $chapter->prechapterid;
        $chapterVipStatus = $chapter->isvip;
        if ($preChapterId && $book->isvip == 1) {
            $preChapterInfo = DB::table('ds_book_chapter')->where('id', $preChapterId)->first();
            if ($preChapterInfo && $preChapterInfo->isvip == 1) {
                $chapterVipStatus = $chapterContentLength > 999 ? 1 : 0;
            }
        }
        if ($chapter->isvip == 1) {
            // 如果当前章节为VIP章节，则进行最后一步判定
            $chapterVipStatus = $chapterContentLength > 999 ? 1 :0;
        }
        try {
            DB::beginTransaction();
            if ($chapter->istemp != 1) {
                // 如果不是草稿
                DB::table('ds_book_info')->where('id', $chapter->bookid)->decrement('bookcnt', $contentLengthGap); // 修改字数
            }
            DB::table('ds_book_chapter')->where('id', $chapter->id)->update([
                'chaptername' => e($request->input('title')),
                'chaptercontent' => e($request->input('content')),
                'sectionid' => $request->input('sectionid'),
                'wordcnt' => $chapterContentLength,
                'isvip' => $chapterVipStatus,
                'updated_at' => now(),
            ]);
            DB::commit();
            return successJson();
        } catch (\Throwable $th) {
            DB::rollBack();
            return errorJson();
        }  
    }
    /**
    * @router /get/book/info/{bookid}
    * @params {}
    * @response { }
    * @name 获取书籍信息和章节列表
    */
    public function getChapterList($bookid)
    {
        if (Redis::get('book:del:'.$bookid)) {
            return errorJson('BOOK_NOT_FOUND');
        }
        $bookCache = unserialize(Redis::get(BookConfig::ReaderBookCacheKey.$bookid));
        if (!$bookCache) {
            $book = Book::find($bookid);
            if (!$book || $book->publishstatus != BookConfig::BookNormalPublishstatus) {
                Redis::setex('book:del:'.$bookid, 24 * 3600, 1);
                return errorJson('BOOK_NOT_FOUND');
            }
            $bookdata = [
                'id' => $book->id,
                'author_id' => $book->userid,
                'bookimg' => $book->bookimg,
                'classid' => $book->classid,
                'bookname' => $book->bookname,
                'writername' => $book->writername,
                'bookintro' => $book->bookintro,
                'role' => $book->bookintro,
                'bookcnt' => $book->bookcnt,
                'endstatus' => $book->endstatus == 1 ? '连载中' : '已完结',
                'publishtime' => $book->publishtime,
                'sign_status' => $book->issign ? '已签约' : '未签约',
                'vip_status' => $book->isvip ?  '收费' : '免费',
                'recomtext' => $book->recomtext,
                'category' => $book->category,
                'class_name' => $book->class_name,
            ];
            Redis::setex(BookConfig::ReaderBookCacheKey.$book->id, BookConfig::ReaderBookCacheEffective, serialize($bookdata));
            $bookCache = $bookdata;
        }
        $chapterList = Cache::remember(BookConfig::ReaderChapterCacheKey.$bookCache['id'], BookConfig::ReaderBookCacheEffective, function()use($bookCache) {
            return DB::table('ds_book_chapter')->where('bookid', $bookCache['id'])->where('status', 1)->select('id', 'sectionid', 'chaptername', 'wordcnt', 'isvip')->get();
        });
        $bookTagIdList = Cache::remember(BookConfig::ReaderBookTagCacheKey.$bookCache['id'], BookConfig::ReaderBookCacheEffective, function()use($bookCache) {
            return DB::table('ds_book_tag_data')->where('bookid', $bookCache['id'])->pluck('tagid');
        });
        $sectionInfo = Cache::remember(BookConfig::ReaderBookSectionCacheKey.$bookCache['id'], BookConfig::ReaderBookCacheEffective, function()use($bookCache) {
            return DB::table('ds_book_sections')->where('bookid', $bookCache['id'])->get();
        });
        $bookTagList = Cache::remember(BookConfig::ReaderBookTagInfoCacheKey.$bookCache['id'], BookConfig::ReaderBookCacheEffective, function()use($bookTagIdList){
            return DB::table('ds_book_tag')->whereIn('id', $bookTagIdList)->select('id', 'tagname')->get();
        });
        $bookExtendIdInfo = Cache::remember(BookConfig::ReaderBookExtendInfoCacheKey, BookConfig::ReaderBookCacheEffective, function()use($bookCache) {
            return DB::table('ds_book_extend_data')->where('bookid', $bookCache['id'])->select('extendcombox1 as ycx', 'extendcombox2 as sk', 'extendcombox3 as sj')->first();
        });
        $returnData = [
            'bookInfo' => $bookCache,
            'chapterList' => $chapterList,
            'bookTag' => $bookTagList,
            'extendInfo' => [
                'ycx' => config('bookextend.e_1_'.$bookExtendIdInfo->ycx),
                'sk' => config('bookextend.e_2_'.$bookExtendIdInfo->sk),
                'sj' => config('bookextend.e_3_'.$bookExtendIdInfo->sj),
            ],
            'sectionList' => $sectionInfo,
        ];
        return successJson($returnData);
    }
    public function getChapterInfo(Request $request)
    {
        $validrules = [
            'bookid' => 'required|numeric',
            'chapterid' => 'required|numeric'
        ];
        $validmessages = [
            'bookid.required' => '您必须选择查阅的作品',
            'bookid.numeric' => '作品ID错误',
            'chapterid.required' => '您必须选择查阅的章节',
            'chapterid.numeric' => '章节ID错误'
        ];
        validateParams($request->all(), $validrules, $validmessages);
        $userInfo = $request->get('userInfo');
        if (!$userInfo) {
            $userInfo = [
                'id' => 0,
            ];
        }
        if (Redis::get('book:del:'.$request->input('bookid'))) {
            return errorJson('BOOK_NOT_FOUND');
        }
        $book = Book::find($request->input('bookid'));
        if (!$book || $book->publishstatus != BookConfig::BookNormalPublishstatus) {
            Redis::setex('book:del:'.$request->input('bookid'), 24 * 3600, 1);
            return errorJson('BOOK_NOT_FOUND');
        }
        $chapterInfo = DB::table('ds_book_chapter')->where('id', $request->input('chapterid'))->select('id', 'bookid', 'chaptername', 'chaptercontent', 'wordcnt', 'isvip', 'status', 'publishtime', 'viewcnt', 'istemp')->first();
        if (!$chapterInfo || $chapterInfo->status != 1) {
            return errorJson('CHAPTER_NOT_FOUND');
        }
        if ($chapterInfo->bookid != $book->id) {
            return errorJson('CHAPTER_NOT_FOUND');
        }
        if ($chapterInfo->istemp) {
            return errorJson('CHAPTER_NOT_FOUND');
        }
        if ($chapterInfo->isvip) {
            if ($userInfo['id'] == 0) {
                return errorJson('ACCESS_CHAPTER_VIP_NEED_LOGIN');
            }
            $checkVipReadAuth = DB::table('ds_order_chapter')->where('userid', $userInfo['id'])->where('bookid', $book->id)->where('chapterid', $chapterInfo->id)->first();
            if (!$checkVipReadAuth) {
                return errorJson('CHAPTER_VIP_NOT_BUY', $book->id);
            }
        }
        // 获取加密章节内容的 key
        $chapterKey = config('app.chapter_key').md5($userInfo['id']);
        // 加密章节内容
        $chapterInfo->chaptercontent = openssl_encrypt($chapterInfo->chaptercontent, 'AES-128-ECB', $chapterKey, 0);
        $data = [
            'bookname' => $book->bookname,
            'chapterInfo' => $chapterInfo
        ];
        return successJson($data);
    }
    public function vipChapterList($bookid)
    {
        $book = Book::find($bookid);
        if (!$book || $book->publishstatus != BookConfig::BookNormalPublishstatus) {
            return errorJson('BOOK_NOT_FOUND');
        }
        $chapterList = DB::table('ds_book_chapter')->where('bookid', $book->id)->where('isvip', 1)->where('istemp', 0)->select('id', 'bookid', 'chaptername', 'wordcnt')->get();
        return successJson($chapterList);
    }
    public function buyVipChapter(Request $request)
    {
        $validrules = [
            'bookid' => 'required|numeric',
            'chapter_id_list' => 'required'
        ];
        $userInfo = $request->get('userInfo');
        if (!is_array($request->input('chapter_id_list'))) {
            return errorJson('IMPOSSIBLE_TO_BUY_VIP_CHAPTER');
        }
        $book = Book::find($request->input('bookid'));
        if (!$book || $book->publishstatus != BookConfig::BookNormalPublishstatus) {
            return errorJson('BOOK_NOT_FOUND');
        }
        $user = User::find($userInfo['id']);
        if (!$user || $user->islock == 1) {
            return errorJson('IMPOSSIBLE_TO_BUY_VIP_CHAPTER');
        }
        $orderChapterInfo = DB::table('ds_order_chapter')->where('userid', $userInfo['id'])->where('bookid', $book->id)->whereIn('chapterid', $request->input('chapter_id_list'))->pluck('chapterid');
        $finalChapterId = $request->input('chapter_id_list');
        foreach ($finalChapterId as $chapterItem) {
            if (in_array($chapterItem, $orderChapterInfo)) {
                unset($chapterItem);
            }
        }
        $filterVipChapterList = DB::table('ds_book_chapter')->whereIn('id', $finalChapterId)->where('bookid', $book->id)->get();
        $buyData = [];
        $finalBuyChapterId = [];
        $totalUseGold = 0;
        foreach ($filterVipChapterList as $chapterItem) {
            $vipOrderData = [
                'userid' => $userInfo['id'],
                'bookid' => $book->id,
                'chapterid' => $chapterItem->id,
                'spendgold' => floor(($chapterItem->wordcnt / 1000) * BookConfig::BuyVipChapterUnitPrice),
                'bookname' => $book->bookname,
                'chaptername' => $chapterItem->chaptername,
            ];
            $totalUseGold += floor(($chapterItem->wordcnt / 1000) * BookConfig::BuyVipChapterUnitPrice);
            array_push($buyData, $vipOrderData);
            array_push($finalBuyChapterId, $chapterItem->id);
        }
        if ($totalUseGold < $user->gold) {
            return errorJson('BUY_VIP_CHAPTER_GOLD_INSUFFICIENT');
        }
        $orderId = generateOrderId($user->id, 4);
        try {
            DB::beginTransaction();
            // 扣除用户订阅总额
            DB::table('ds_user_info')->where('id', $user->id)->decrement('gold', $totalUseGold);
            DB::table('ds_order_chapter')->insert($buyData);
            DB::commit();
            return successJson($vipOrderData[0]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return errorJson();
        }
    }
}
