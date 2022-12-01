<?php

namespace App\Http\Controllers\Api\CP;

use Illuminate\Http\Request;
use App\Exceptions\ShanHaiCPException;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class IndexController extends Controller
{
  public function getBookList(Request $request)
  {
    $key = $request->get('param_key');
    $token = $request->get('param_token');
    $channelInfo = $request->get('channelInfo');
    $action = 'getbooklist';
    generateShanhaiChannelToken($key, $channelInfo['secret'], $action, $token);
    $bookList = DB::table('ds_channel_book')->where('channelid', $channelInfo['id'])->select('bookid', 'bookname')->get();
    return shanhaiSuccessJson($bookList);
  } 
  public function getBookInfo(Request $request) {
    $key = $request->get('param_key');
    $token = $request->get('param_token');
    $channelInfo = $request->get('channelInfo');
    $action = 'getbookinfo';
    $data = [
      'bookid' => isset($_GET['bookid']) ? $_GET['bookid'] : 0,
    ];
    generateShanhaiChannelToken($key, $channelInfo['secret'], $action, $token, $data);
    $bookAuthorizeInfo = DB::table('ds_channel_book')->where('channelid', $channelInfo['id'])->where('bookid', $data['bookid'])->first();
    if (!$bookAuthorizeInfo) {
      throw new ShanHaiCPException('未授权本书籍');
    }
    $bookInfo = Cache::remember('qudao_book:'.$bookAuthorizeInfo->bookid, 3600, function()use($bookAuthorizeInfo) {
      return DB::table('ds_book_info')->where('ds_book_info.id', $bookAuthorizeInfo->bookid)->leftJoin('ds_book_class', 'ds_book_class.id', '=', 'ds_book_info.classid')->select('ds_book_info.*', 'ds_book_class.pid')->first();
    });
    if (!$bookInfo || $bookInfo->publishstatus != 1) {
      throw new ShanHaiCPException('书籍信息丢失');
    }
    $channelClassInfo = Cache::remember('qudao_book_class:user_'.$channelInfo['id'].'_'.$bookInfo->classid, 3600, function()use($channelInfo, $bookInfo) {
      return DB::table('ds_channel_classmap')->where('channelid', $channelInfo['id'])->where('classid', $bookInfo->classid)->first();
    });
    if (!$channelClassInfo) {
      $channelClassId = 0;
    } else {
      $channelClassId = $channelClassInfo->channelclassid;
    }
    $bookData = [
      'bookid' => $bookInfo->id,
      'bookname' => $bookInfo->bookname,
      'author' => $bookInfo->writername,
      'endstatus' => $bookInfo->endstatus,
      'bookcnt' => $bookInfo->bookcnt,
      'bookintro' => $bookInfo->bookintro,
      'tags' => $bookInfo->booktag,
      'role' => $bookInfo->role,
      'bookimg' => $bookInfo->bookimg,
      'classid' => $bookInfo->classid,
      'category' => $bookInfo->pid,
      'channelclassid' => $channelClassId,
      'lastupdatetime' => $bookinfo->lastchapterupdatetime,
      'lastchapterid' => $bookInfo->lastchapterid,
    ];
    return shanhaiSuccessJson($bookData);
  }
  public function getChapterList(Request $request)
  {
    $key = $request->get('param_key');
    $token = $request->get('param_token');
    $channelInfo = $request->get('channelInfo');
    $action = 'getbookchapterlist';
    $data = [
      'bookid' => isset($_GET['bookid']) ? $_GET['bookid'] : 0,
      'chapterid' => isset($_GET['chapterid']) ? $_GET['chapterid'] : 0,
    ];
    generateShanhaiChannelToken($key, $channelInfo['secret'], $action, $token, $data);
    $bookAuthorizeInfo = DB::table('ds_channel_book')->where('channelid', $channelInfo['id'])->where('bookid', $data['bookid'])->first();
    if (!$bookAuthorizeInfo) {
      throw new ShanHaiCPException('未授权本书籍');
    }
    $chapterList = DB::table('ds_book_chapter')->where('bookid', $bookAuthorizeInfo->id)->where('istemp', 0)->where('status', 1)->select('id as chhapterid', 'chaptername', 'isvip')->get();
    return shanhaiSuccessJson($chapterList);
  }
  public function getChapterInfo(Request $request)
  {
    $key = $request->get('param_key');
    $token = $request->get('param_token');
    $channelInfo = $request->get('channelInfo');
    $action = 'getbookchapter';
    $data = [
      'bookid' => isset($_GET['bookid']) ? $_GET['bookid'] : 0,
      'chapterid' => isset($_GET['chapterid']) ? $_GET['chapterid'] : 0,
    ];
    generateShanhaiChannelToken($key, $channelInfo['secret'], $action, $token, $data);
    $bookAuthorizeInfo = DB::table('ds_channel_book')->where('channelid', $channelInfo['id'])->where('bookid', $data['bookid'])->first();
    if (!$bookAuthorizeInfo) {
      throw new ShanHaiCPException('未授权本书籍');
    }
    $chapterInfo = DB::table('ds_book_chapter')->where('id', $data['chapterid'])->first();
    if (!$chapterInfo || $chapterInfo->status == 0) {
      throw new ShanHaiCPException('章节信息丢失');
    }
    if ($chapterInfo->istemp) {
      throw new ShanHaiCPException('此章节不存在');
    }
    $chapterData = [
      'chapterid' => $chapterInfo->id,
      'chaptername' => $channelInfo->chaptername,
      'chaptercontent' => $channelInfo->chaptercontent
    ];
    return shanhaiSuccessJson($chapterData);
  }
  public function getBookClass(Request $request)
  {
    $key = $request->get('param_key');
    $token = $request->get('param_token');
    $channelInfo = $request->get('channelInfo');
    generateShanhaiChannelToken($key, $channelInfo['secret'], 'getbookclass', $token);
    $classList = Cache::remember('qudao_class:list', 3600 * 7, function() {
      return DB::table('ds_book_class')->select('id as classid', 'classname')->get();
    });
    return shanhaiSuccessJson($classList);
  }
}
