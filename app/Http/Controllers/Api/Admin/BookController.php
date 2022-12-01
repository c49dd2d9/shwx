<?php

namespace App\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use App\Exceptions\AdminApiException;
use App\Models\Book;
use App\Enums\BookConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\Controller;

class BookController extends Controller
{
  public function getBookInfo(Request $request)
  {
    $validrules = [
      'bookid' => 'required|numeric'
    ];
    validateParams($request->all(), $validrules);
    $book = Book::find($request->input('bookid'));
    if (!$book) {
      throw new AdminApiException('BOOK_DATA_NOT_FOUND');
    }
    return successJson($book);
  }
  public function getBookList(Request $request)
  {
    /**
     * 获取作品，可以选择获取回收站、已审核的作品
     * 可以根据id、文名、作者名、作者id查询
     */
    $validrules = [
      'search_field' => 'required',
      'keyword' => 'required',
      'publishstatus' => 'required|numeric'
    ];
    validateParams($request->only('search_field', 'keyword', 'publishstatus'), $validrules);
    $searchFieldAllowList = [ 'id', 'bookname', 'writername' , 'userid' ];
    if (!in_array($request->input('search_field'), $searchFieldAllowList)) {
      throw new AdminApiException('DENY_ALLOW_THIS_SEARCH_FIELD');
    }
    $publishStatusAllowList = [ 1, 2, 4 ];
    if (!in_array($request->input('publishstatus'), $publishStatusAllowList)) {
      throw new AdminApiException('DENY_ALLOW_THIS_SEARCH_FIELD');
    }
    $keyword = e($request->input('keyword'));
    if ($request->input('search_field') == 'id') {
      $bookList = DB::table('ds_book_info')->where('id', $keyword)->where('publishstatus',$publishStatusAllowList)->paginate(50);
    } else {
      $bookList = DB::table('ds_book_info')->where('publishstatus',$publishStatusAllowList)->where($request->input('search_field'), 'like', '%'.$keyword.'%')->paginate(50);
    }    
    return successJson($bookList);
  }
  public function changeBookInfo(Request $request)
  {
    $validrules = [
      'bookid' => 'required|numeric',
      'bookname' => 'required',
      'endstatus' => 'required|boolean',
      'publishstatus' => 'required|numeric',
      'bookintro' => 'required',
      'bookimg' => 'required',
      'classid' => 'required',
      'role' => 'required'
    ];
    validateParams($request->all(), $validrules);
    $book = Book::find($request->input('bookid'));
    if (!$book) {
      throw new AdminApiException('BOOK_DATA_NOT_FOUND');
    }
    $publishStatusAllowList = [ 1, 2, 4 ];
    if (!in_array($request->input('publishstatus'), $publishStatusAllowList)) {
      throw new AdminApiException('DENY_ALLOW_THIS_SEARCH_FIELD');
    }
    $categoryName = $book->category;
    $className = $book->class_name;
    if ($request->input('classid') != $book->classid) {
      // 如果传入的class id 不等于当前的class id
      $classInfo = DB::table('ds_book_class')->where('id', $request->input('classid'))->first();
      if (!$classInfo) {
        throw new AdminApiException('CLASS_DATA_NOT_FOUND');
      }
      $categoryInfo = DB::table('ds_book_category')->where('id', $classInfo->categoryid)->first();
      if (!$categoryInfo) {
        throw new AdminApiException('CLASS_DATA_NOT_FOUND');
      }
      $categoryName = $categoryInfo->categoryname;
      $className = $classInfo->classname;
    }
    DB::table('ds_book_info')->where('id', $book->id)->update([
      'bookname' => e($request->input('bookname')),
      'bookintro' => e($request->input('bookintro')),
      'endstatus' => $request->input('endstatus'),
      'publishstatus' => $request->input('publishstatus'),
      'bookimg' => $request->input('bookimg'),
      'classid' => $request->input('classid'),
      'role' => $request->input('role'),
    ]);
    return successJson();
  }
  public function getChapterList($bookid)
  {
    $chapterList = DB::table('ds_book_chapter')->where('bookid', $bookid)->paginate(30);
    return successJson($chapterList);
  }
  public function getChapterInfo($chapterid)
  {
    $chapterInfo = DB::table('ds_book_chapter')->where('id', $chapterid)->first();
    if (!$chapterInfo) {
      throw new AdminApiException('CHAPTER_INFO_NOT_FOUND');
    }
    $sectionInfo = DB::table('ds_book_sections')->where('bookid', $chapterInfo->id)->get();
    $data = [
      'chapterInfo' => $chapterInfo,
      'sectionInfo' => $sectionInfo,
    ];
    return successJson($sectionInfo);
  }
  public function updateChapterInfo(Request $request)
  {
    $validrules = [
      'chapterid' => 'required|numeric',
      'title' => 'required',
      'content' => 'required',
      'isvip' => 'required|boolean',
      'status' => 'required|boolean',
      'sectionid' => 'required|numeric',
    ];
    validateParams($request->all(), $validrules);
    // 管理员手动定义isVip，此处不处理字数逻辑
    $chapterInfo = DB::table('ds_book_chapter')->where('id', $request->input('chapterid'))->first();
    if (!$chapterInfo) {
      throw new AdminApiException('CHAPTER_INFO_NOT_FOUND');
    }
    if ($chapterInfo->sectionid != $request->input('sectionid')) {
      $sectionInfo = DB::table('ds_book_sections')->where('id', $request->input('sectionid'))->first(); 
      if (!$sectionInfo || $sectionInfo->bookid != $chapterInfo->bookid) {
        throw new AdminApiException('BOOK_SECTION_INFO_FALSE');
      }
    }    
    $chapterContentLength = comment_count_word($request->input('content'));
    $newStatus = $request->input('status') == true ? 1 : 0;
    try {
      DB::beginTransaction();
      if ($chapterContentLength != $chapterInfo->wordcnt && $chapterInfo->istemp == 0) {
        DB::table('ds_book_info')->where('id', $chapterInfo->bookid)->decrement('bookcnt', $chapterInfo->wordcnt);
        DB::table('ds_book_info')->where('id', $chapterInfo->bookid)->increment('bookcnt', $chapterContentLength);
      }
      DB::table('ds_book_chapter')->where('id', $chapterInfo->id)->update([
        'chaptername' => e($request->input('title')),
        'chaptercontent' => e($request->input('content')),
        'isvip' => $request->input('isvip') == true ? 1 : 0,
        'status' => $newStatus,
        'sectionid' => $request->input('sectionid'),
      ]);
      if ($newStatus != $chapterInfo->status && $chapterInfo->istemp == 0) {
        if ($newStatus == 0) {
          // 如果是删除
          DB::table('ds_book_info')->where('id', $chapterInfo->bookid)->decrement('chaptercnt', 1);
        } else {
          // 如果是恢复
          DB::table('ds_book_info')->where('id', $chapterInfo->bookid)->increment('chaptercnt', 1);
        }
      }
      DB::commit();
      return successJson();
    } catch (\Throwable $th) {
      DB::rollBack();
      throw new AdminApiException('CHAPTER_INFO_CHANGE_FAILED');
    }
  }
  public function publishTempChapter($chapterid)
  {
    $chapterInfo = DB::table('ds_book_chapter')->where('id', $chapterid)->first();
    if (!$chapterInfo) {
      throw new AdminApiException('CHAPTER_INFO_NOT_FOUND');
    }
    if ($chapterInfo->istemp == 0) {
      throw new AdminApiException('CHAPTER_IS_NOT_TEMP');
    }
    $book = Book::find($chapterInfo->bookid);
    if (!$book) {
      throw new AdminApiException('BOOK_DATA_NOT_FOUND');
    }
    if ($chapterInfo->publishtime == '0000-00-00 00:00:00') {
      $newPublishTime = now();
    } else {
      $newPublishTime = $chapterInfo->publishtime;
    }
    try {
      DB::beginTransaction();
      DB::table('ds_book_chapter')->where('id', $chapterInfo->id)->update([
        'istemp' => 0,
        'istimepublish' => 0,
        'status' => 1,
        'haspublished' => 1,
        'publishtime' => $newPublishTime
      ]);
      DB::table('ds_daliy_update_book')->insert([
        'bookid' => $book->id,
        'created_at' => now(),
        'updated_at' => now(),
      ]);
      DB::table('ds_book_info')->where('id', $book->id)->increment('bookcnt', $chapterInfo->wordcnt, ['lastchapterid' => $chapterInfo->id, 'lastchapterupdatetime' => $newPublishTime]);
      DB::table('ds_book_info')->where('id', $book->id)->increment('chaptercnt', 1);
      DB::commit();
      return successJson();
    } catch (\Throwable $th) {
      DB::rollBack();     
      throw new AdminApiException('CHAPTER_INFO_CHANGE_FAILED');
    }
  }
  public function chapterPiliangAction(Request $request)
  {
    $validrules = [
      'moduleid' => 'required|numeric',
      'chapter_id_list' => 'required',
      'bookid' => 'required|numeric',
      'status' => 'required|boolean'
    ];
    validateParams($request->all(), $validrules);
    /**
     * 1 批量删除或恢复
     * 2 批量设定为Vip
     */
    $allowModuleIdList = [ 1, 2 ];
    if (!in_array($request->input('moduleid'), $allowModuleIdList)) {
      throw new AdminApiException('PILIANG_MODULE_ID_DENY_ALLOW');
    }
    if (!is_array($request->input('chapter_id_list'))) {
      throw new AdminApiException('PILIANG_MODULE_ID_DENY_ALLOW');
    }
    $book = Book::find($request->input('bookid'));
    if (!$book || $book->publishstatus == 4) {
      throw new AdminApiException('BOOK_DATA_NOT_FOUND');
    }
    $chapterIdList = DB::table('ds_book_chapter')->whereIn('id', $request->input('chapter_id_list'))->where('bookid', $request->input('bookid'))->pluck('id');
    switch ($request->input('moduleid')) {
      case 1:
        // 批量删除或恢复
        $getChapterContentLength = DB::table('ds_book_chapter')->where('bookid', $book->id)->where('istemp', 0)->where('status', 1)->sum('wordcnt');
        try {
          DB::beginTransaction();
          DB::table('ds_book_chapter')->whereIn('id', $chapterIdList)->update([
            'status' => $request->input('status') == true ? 1 : 0,
          ]);
          if ($request->input('status') == true) {
            DB::table('ds_book_info')->where('id', $book->id)->increment('chaptercnt', count($chapterIdList), [ 'bookcnt' => $getChapterContentLength]);
          } else {
            DB::table('ds_book_info')->where('id', $book->id)->decrement('chaptercnt', count($chapterIdList), [ 'bookcnt' => $getChapterContentLength]);
          }
          DB::commit();
          return successJson();
        } catch (\Throwable $th) {
          throw new AdminApiException('PILIANG_CHAPTER_LIST_INFO_CHANGE_FAILED');
        }
        break;
      case 2:
        // 批量设置VIP或者解VIP
        DB::table('ds_book_chapter')->whereIn('id', $chapterIdList)->update([
          'isvip' => $request->input('status') == true ? 1 : 0
        ]);
        return successJson();
        break;
      default:
        throw new AdminApiException('PILIANG_MODULE_ID_DENY_ALLOW');
        break;
    }
  }
  public function getApplySignBook()
  {
    $bookList = DB::table('ds_apply_sign')->where('status', 0)->orderBy('id', 'desc')->paginate(30);
    return successJson($bookList);
  }
  public function checkApplySignBook(Request $request)
  {
    $validrules = [
      'applyid' => 'required|numeric',
      'status' => 'required|boolean',
    ];
    validateParams($request->all(), $validrules);
    $applyInfo = DB::table('ds_apply_sign')->where('id', $request->input('applyid'))->first();
    if (!$applyInfo) {
      throw new AdminApiException('CAN_NOT_FOUND_THIS_APPLY_INFO');
    }
    if ($applyInfo->status != 0) {
      throw new AdminApiException('THIS_APPLY_INFO_PROCESSED');
    }
    $status = $request->input('status') == true ? 1 : 2;
    $reason = e($request->input('reason')) ? $request->input('reason') : '无理由';
    $title = $status == 1 ? '您的申请签约已通过' : '您的作品未通过签约审核';
    $allUUID = md5($applyInfo->bookname.rand(10000, 99999));
    $content = $status == 1 ? '尊敬的作者您好，您的作品《'.$applyInfo->bookname.'》已通过签约审核，请联系责任编辑QQ670040436。添加好友时请注明过签作品的书名，洽谈合约期间请尽量保持作品稳定更新。请注意完善您的个人信息、支付信息。' : '尊敬的作者您好，很遗憾您的作品《'.$applyInfo->bookname.'》未通过签约审核，感谢您在山海文学发文。写文不易，请您不要气馁哦~';
    try {
      DB::beginTransaction();
      DB::table('ds_apply_sign')->where('id', $applyInfo->id)->update([
        'status' => $status,
        'reason' => $reason,
      ]);
      DB::table('ds_user_notify_data')->insert([
        'userid' => $applyInfo->userid,
        'title' => $title,
        'content' => $content,
        'read_status' => 0,
        'type' => 2,
        'all_uuid' => $allUUID
      ]);
      DB::table('ds_admin_search_notify_data')->insert([
        'title' => $title,
        'content' => $content,
        'all_uuid' => $allUUID,
      ]);
      DB::commit();
      return successJson();
    } catch (\Throwable $th) {
      DB::rollBack();
      throw new AdminApiException('DEFAULT_ERROR');
    }
  }
  public function signBook(Request $request)
  {
    $validrules = [
      'bookid' => 'required|numeric',
      'enddate' => 'required',
      'fencheng' => 'required',
      'daojufencheng' => 'required',
      'remark' => 'required|max:30',
    ];
    $userInfo = $request->get('userInfo');
    validateParams($request->all(), $validrules);
    $book = Book::find($request->input('bookid'));
    if (!$book) {
      throw new AdminApiException('BOOK_DATA_NOT_FOUND');
    }
    if ($book->issign == 1) {
      throw new AdminApiException('ADMIN_BOOK_IS_SIGNED');
    }
    $authorInfo = DB::table('ds_user_authorinfo')->where('userid', $book->userid)->first();
    if (!$authorInfo) {
      throw new AdminApiException('AUTHOR_INFO_IMPERFECT');
    }
    // 判断真实姓名是否为空
    checkSignAuthorInfoValueisNull($authorInfo->name);
    // 判断qq是否为空
    checkSignAuthorInfoValueisNull($authorInfo->qq);
    // 判断地址是否为空
    checkSignAuthorInfoValueisNull($authorInfo->address);
    // 判断身份证信息是否为空
    checkSignAuthorInfoValueisNull($authorInfo->idcard);
    $authorBankInfo = DB::table('ds_user_bankinfo')->where('userid', $book->userid)->first();
    if (!$authorBankInfo) {
      throw new AdminApiException('AUTHOR_INFO_IMPERFECT');
    }
    // 判断手机号是否为空
    checkSignAuthorInfoValueisNull($authorBankInfo->contactphone);
    // 判断开户人是否为空
    checkSignAuthorInfoValueisNull($authorBankInfo->bankuser);
    // 判断开户行是否为空
    checkSignAuthorInfoValueisNull($authorBankInfo->bankname);
    // 判断银行卡号是否为空
    checkSignAuthorInfoValueisNull($authorBankInfo->bankaccount);
    try {
      DB::beginTransaction();
      DB::table('ds_book_signinfo')->insert([
        'bookid' => $book->id,
        'signtype' => 1,
        'endtype' => 0,
        'endday' => $request->input('enddate'),
        'dyrate' => 100,
        'fcrate' => $request->input('fencheng'),
        'giftfcrate' => $request->input('daojufencheng'),
        'editorid' => $userInfo['id'],
        'remark' => e($request->input('remark')),
      ]);
      $book->issign = 1;
      $book->save();
      DB::commit();
      return successJson();
    } catch (\Throwable $th) {
      DB::rollBack();
     throw new AdminApiException('DEFAULT_ERROR');
    }
  }
  public function repealSignStatus(Request $request)
  {
    $validrules = [
      'bookid' => 'required|numeric',
      'reason' => 'required|max:30',
    ];
    validateParams($request->only('bookid', 'reason'), $validrules);
    $signInfo = DB::table('ds_book_signinfo')->where('bookid', $request->input('bookid'))->first();
    if (!$signInfo) {
      throw new AdminApiException('BOOK_SIGN_STATUS_ABOLISHED');
    }
    if ($signInfo->signstatus == 0) {
      throw new AdminApiException('BOOK_SIGN_STATUS_ABOLISHED');
    }
    $book = Book::find($signInfo->bookid);
    if (!$book) {
      throw new AdminApiException('BOOK_DATA_NOT_FOUND');
    }
    try {
      DB::beginTransaction();
      DB::table('ds_book_signinfo')->where('id', $signInfo->id)->update([
        'signstatus' => 0,
        'remark' => $request->input('reason'),
      ]);
      $book->issign = 0;
      $book->save();
      DB::commit();
      return successJson();
    } catch (\Throwable $th) {
      DB::rollBack();
      throw new AdminApiException('DEFAULT_ERROR');
    }
  }
  public function getBookTicketData(Request $request)
  {
    $validrules = [
      'bookid' => 'numeric',
      'type' => 'required'
    ];
    validateParams($request->only('bookid', 'type'), $validrules);
    switch ($request->input('type')) {
      case 'yuepiao':
        if (!$request->input('bookid') || $request->input('bookid') <= 0) {
          $result = DB::table('ds_user_yplog')->leftJoin('ds_user_info', 'ds_user.yplog.userid', '=', 'ds_user_info.id')->select('ds_user_yplog.yuepiao as piao_count', 'ds_user_info.id', 'ds_user_info.nickname', 'ds_user_yplog.logtime as created_at')->orderBy('ds_user_yplog.id', 'desc')->paginate(100);
        } else {
          $result = DB::table('ds_user_yplog')->where('ds_user_yplog.bookid', $request->input('bookid'))->leftJoin('ds_user_info', 'ds_user.yplog.userid', '=', 'ds_user_info.id')->select('ds_user_yplog.yuepiao as piao_count', 'ds_user_info.id', 'ds_user_info.nickname', 'ds_user_yplog.logtime as created_at')->orderBy('ds_user_yplog.id', 'desc')->paginate(100);
        }
        break;
      case 'tuijianpiao':
        if (!$request->input('bookid') || $request->input('bookid') <= 0) {
          $result = DB::table('ds_user_piao')->where('ds_user_piao.listtype', 2)->leftJoin('ds_user_info', 'ds_user_piao.userid', '=', 'ds_user_info.id')->select('ds_user_piao.cnt as piao_count', 'ds_user_info.id', 'ds_user_info.nickname', 'ds_user_piao.logtime as created_at')->orderBy('ds_user_piao.id', 'desc')->paginate(100);
        } else {
          $result = DB::table('ds_user_piao')->where('ds_user_piao.bookid', $request->input('bookid'))->where('ds_user_piao.listtype', 2)->leftJoin('ds_user_info', 'ds_user_piao.userid', '=', 'ds_user_info.id')->select('ds_user_piao.cnt as piao_count', 'ds_user_info.id', 'ds_user_info.nickname', 'ds_user_piao.logtime as created_at')->orderBy('ds_user_piao.id', 'desc')->paginate(100);
        }
        break;
      case 'cuigengpiao':
        if (!$request->input('bookid') || $request->input('bookid') <= 0) {
          $result = DB::table('ds_user_piao')->where('ds_user_piao.listtype', 1)->leftJoin('ds_user_info', 'ds_user_piao.userid', '=', 'ds_user_info.id')->select('ds_user_piao.cnt as piao_count', 'ds_user_info.id', 'ds_user_info.nickname', 'ds_user_piao.logtime as created_at')->orderBy('ds_user_piao.id', 'desc')->paginate(100);
        } else {
          $result = DB::table('ds_user_piao')->where('ds_user_piao.bookid', $request->input('bookid'))->where('ds_user_piao.listtype', 1)->leftJoin('ds_user_info', 'ds_user_piao.userid', '=', 'ds_user_info.id')->select('ds_user_piao.cnt as piao_count', 'ds_user_info.id', 'ds_user_info.nickname', 'ds_user_piao.logtime as created_at')->orderBy('ds_user_piao.id', 'desc')->paginate(100);
        }
        break;
      default:
        throw new AdminApiException('DENY_ALLOW_TICKET_NAME');
        break;
    }
    return successJson($result);
  }
  public function getBookGiftData(Request $request)
  {
    $validrules = [
      'bookid' => 'numeric',
    ];
    $bookId = $request->input('bookid') ? $request->input('bookid') : 0;
    $page = isset($_GET['page']) ? $_GET['page'] : 1;
    if ($bookId <= 0) {
      $giftList = Cache::remember('admin:book:gift:data:all_'.$page, 3600, function() {
        return DB::table('ds_gift_data')->leftJoin('ds_user_info', 'ds_gift_data.userid', '=', 'ds_user_info.id')->leftJoin('ds_book_info', 'ds_gift.data.bookid', '=', 'ds_book_info.id')->leftJoin('ds_gift_info', 'ds_gift_data.propid', '=', 'ds_gift_info.id')->select('ds_gift_data.message', 'ds_gift_data.number', 'ds_gift_data.logtime as created_at', 'ds_user_info.nickname', 'ds_book_info.bookname', 'ds_gift_info.name')->paginate(100);
      });
    } else {
      $giftList = Cache::remember('admin:book:gift:data:'.$bookId.'_'.$page, 3600, function()use($bookId) {
        return DB::table('ds_gift_data')->where('ds_gift_data.bookid', $bookId)->leftJoin('ds_user_info', 'ds_gift_data.userid', '=', 'ds_user_info.id')->leftJoin('ds_book_info', 'ds_gift.data.bookid', '=', 'ds_book_info.id')->leftJoin('ds_gift_info', 'ds_gift_data.propid', '=', 'ds_gift_info.id')->select('ds_gift_data.message', 'ds_gift_data.number', 'ds_gift_data.logtime as created_at', 'ds_user_info.nickname', 'ds_book_info.bookname', 'ds_gift_info.name')->paginate(100);
      });
    }
    return successJson($giftList);
  }
  public function getBookVipChapterRecord(Request $request)
  {
    $validrules = [
      'bookid' => 'numeric',
    ];
    $page = isset($_GET['page']) ? $_GET['page'] : 1;
    $bookId = $request->input('bookid') ? $request->input('bookid') : 0;
    if ($bookid <= 0) {
      $vipRecordList = Cache::remember('admin_vip:chapter:record:all_'.$page, 3600, function() {
        return  DB::table('ds_order_chapter')->leftJoin('ds_book_info', 'ds_order_chapter.bookid', '=', 'ds_book_info.id')->leftJoin('ds_book_chapter', 'ds_order_chapter.chapterid', '=', 'ds_book_chapter.id')->select('ds_order_chapter.*', 'ds_book_info.bookname', 'ds_book_chapter.chaptername')->paginate(100);
      });
    } else {
      $vipRecordList = Cache::remember('admin_vip:chapter:record:'.$bookId.'_'.$page, 3600, function()use($bookId) {
        return DB::table('ds_order_chapter')->where('ds_order_chapter.bookid', $bookId)->leftJoin('ds_book_info', 'ds_order_chapter.bookid', '=', 'ds_book_info.id')->leftJoin('ds_book_chapter', 'ds_order_chapter.chapterid', '=', 'ds_book_chapter.id')->select('ds_order_chapter.*', 'ds_book_info.bookname', 'ds_book_chapter.chaptername')->paginate(100);
      });
    }
    return successJson($vipRecordList);
  }
}
