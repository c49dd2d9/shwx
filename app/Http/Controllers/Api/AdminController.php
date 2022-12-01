<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Enums\RecommendConfig;
use App\Models\Notice;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use App\Models\Book;
use App\Exceptions\AdminApiException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use App\Http\Controllers\Controller;

// AdminController- 此处有公告、设置/删除榜单
class AdminController extends Controller
{
  public function createNotice(Request $request)
  {
    $validrules = [
      'title' => 'required|max:100',
      'content' => 'required|max:3000',
      'is_author' => 'required',
    ];
    // 验证器拦截
    validateParams($request->only('title', 'content', 'is_author'), $validrules);
    $notice = new Notice;
    $notice->title = $request->input('title');
    $notice->content = $request->input('content');
    $notice->is_author = $request->input('is_author') == true ? 1 : 0;
    return successJson();
  }
  public function getNoticeList(Request $request)
  {
    $notice = Notice::all();
    return successJson($notice);
  }
  public function getNoticeInfo(Request $request)
  {
    $noticeId = $request->input('id');
    if (!$noticeId || $noticeId == 0) {
      throw new AdminApiException('NOTICE_CANNOT_NULL');
    }
    $notice = Notice::find($noticeId);
    if (!$notice) {
      throw new AdminApiException('NOTICE_NOT_FOUND');
    }
    return successJson($notice);
  }
  public function updateNoticeInfo(Request $request)
  {
    $validrules = [
      'title' => 'required|max:100',
      'content' => 'required|max:3000',
      'notice_id' => 'required',
    ];
    validateParams($request->only('title', 'content', 'notice_id'), $validrules);
    $notice = Notice::find($request->input('notice_id'));
    if (!$notice) {
      throw new AdminApiException('NOTICE_NOT_FOUND');
    }
    Notice::where('id', $notice->id)->update([
      'title' => $request->input('title'),
      'content' => $request->input('content'),
    ]);
    return successJson();
  }
  public function deleteNotice(Request $request)
  {
    $noticeId = $request->input('id');
    if (!$noticeId || $noticeId == 0) {
      throw new AdminApiException('NOTICE_CANNOT_NULL');
    }
    $notice = Notice::find($noticeId);
    if (!$notice) {
      throw new AdminApiException('NOTICE_NOT_FOUND');
    }
    if ($notice->is_author == 1) {
      Redis::del('notice:index:author:index');
    } else {
      Redis::del('notice:index:index');
    }
    Notice::destroy($notice->id);
    return successJson();
  }
  public function addRecommend(Request $request)
  {
    $validrules = [
      'recommend_name' => 'required',
      'additional' => 'max:20',
      'bookid' => 'required',
      'index_name' => 'required',
      'recommend_img' => 'max:255',
    ];
    validateParams($request->only('recommend_name', 'additional', 'bookid', 'index_name', 'recommend_img'), $validrules);
    $recommendName = $request->input('recommend_name'); // 当前准备设置的推荐信息
    $recommendList = RecommendConfig::getKeys(); //获取所有推荐Key;
    if (!in_array($recommendName, $recommendList)) {
      throw new AdminApiException('RECOMMEND_INFO_NOT_FOUND');
    }
    $book = DB::table('ds_book_info')->where('id', $request->input('bookid'))->first();
    if (!$book || $book->publishstatus != 1) {
      throw new AdminApiException('ADMIN_CAN_NOT_ADD_RECOMMEND_TO_THIS_BOOK');
    }
    $recommendInfo = RecommendConfig::getValue($recommendName);
    $recommendCount = DB::table('ds_book_recommend')->where('recommendid', $recommendInfo['id'])->count();
    if ($recommendCount >= $recommendInfo['count']) {
      throw new AdminApiException('RECOMMEND_COUNT_MAX');
    }
    $newIntro = comment_count_word($book->recomtext) > 0 ? $book->recomtext : $book->bookintro;
    $tagArray = explode(',', $book->book_tag);
    if (count($tagArray) > 2) {
      $newTag = Arr::where($tagArray, function($value){
        return comment_count_word($value) < 5;
      });
      if (count($newTag) > 4) {
        $newTag = Arr::random($newTag, 4);
      }
    } else {
      $newTag = $tagArray;
    }
    $bookInfo = [
      'name' => $book->bookname,
      'intro' => Str::limit($newIntro, 40, ''),
      'bookImg' => $book->bookimg,
      'writerName' => $book->writername,
      'userId' => $book->userid,
      'className' => $book->class_name,
      'tag' => $newTag,
      'recommend' => $request->input('additional'),
    ];
    $bookInfo = json_encode($bookInfo);
    $index = $request->input('index_name');
    DB::table('ds_book_recommend')->insert([
      'bookid' => $book->id,
      'bookinfo' => $bookInfo,
      'recommendid' => $recommendInfo['id'],
      'classindex' => $index ? $index : 'none',
      'recommend_img' => $request->input('recommend_img') ? $request->input('recommend_img') : '',
      'created_at' => now(),
      'updated_at' => now()
    ]);
    return successJson();
  }
  public function deleteRecommend(Request $request)
  {
    $validrules = [
      'recommendid' => 'required',
      'bookid' => 'required'
    ];
    validateParams($request->only('recommendid', 'bookid'), $validrules);
    $recommendInfo = DB::table('ds_book_recommend')->where('id', $request->input('recommnedid'))->first();
    if (!$recommendInfo) {
      throw new AdminApiException('RECOMMEND_NOT_FOUND');
    }
    if ($recommendInfo->bookid != $request->input('bookid')) {
      throw new AdminApiException('RECOMMEND_NOT_FOUND');
    }
    DB::table('ds_book_recommend')->where('id', $recommendInfo->id)->delete();
    return successJson();
  }
  public function getRecommendList(Request $request)
  {
    $validrules = [
      'recommend_name' => 'required',
    ];
    validateParams($request->only('recommend_name'), $validrules);
    $recommendInfo = RecommendConfig::getValue($request->input('recommend_name'));
    $bookList = DB::table('ds_book_recommend')->where('recommendid', $recommendInfo['id'])->get();
    return successJson($bookList);
  }
  public function setBannerTime(Request $request)
  {
    $validrules = [
      'new_time' => 'required|numeric|min:1|max:10',
    ];
    validateParams($request->only('new_time'), $validrules);
    $newTime = $request->input('new_time') * 1000;
    Redis::set('banner:autopay_delay', $newTime);
    return successJson();
  }
}
