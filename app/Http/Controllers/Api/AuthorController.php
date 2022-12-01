<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\Book;
use App\Enums\BookConfig;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

// 作者Controller
class AuthorController extends Controller
{
    /**
     * @router /change/author/info
     * @params { intro, name, qq, weixin, idcard, address }
     * @response {  }
     * @name 修改作者个人信息
     */
    public function changeAuthorInfo(Request $request)
    {
      $validrules = [
        'intro' => 'required|max:200',
        'name' => 'required|max:32|min:2',
        'qq' => 'required|numeric',
        'weixin' => 'required|max:30',
        'idcard' => 'required',
        'address' => 'required',
      ];
      validateParams($request->only('intro', 'name', 'qq', 'weixin', 'idcard', 'address'), $validrules);
      $userInfo = $request->get('userInfo');
      $authorInfo = $request->get('authorInfo');
      $book = DB::table('ds_book_info')->where('userid', $userInfo['id'])->where('issign', 1)->first();
      if ($book) {
        return errorJson('SIGNED_AUTHOR_CAN_NOT_CHANGE_INFO');
      }
      DB::table('ds_user_authorinfo')->where('id', $authorInfo['id'])->update([
        'intro' => $request->input('intro'),
        'name' => $request->input('name'),
        'qq' => $request->input('qq'),
        'weixin' => $request->input('weixin'),
        'idcard' => $request->input('idcard'),
        'address' => $request->input('address'),
        'logtime' => now()
      ]);
      return successJson();
    }
    /**
     * @router /create/author/pay/info
     * @params { contactphone, bankuser, bankname, backaccount }
     * @response {  }
     * @name 创建作者支付信息
     */
    public function createAuthorPayInfo(Request $request)
    {
      $validrules = [
        'contactphone' => 'required|regex:/(1)[0-9]{10}/',
        'bankuser' =>  'required',
        'bankname' => 'required',
        'bankaccount' => 'required'
      ];
      validateParams($request->only('contactphone', 'bankuser', 'bankname', 'bankaccount'), $validrules);
      $userInfo = $request->get('userInfo');
      $authorBankInfo = DB::table('ds_user_bankinfo')->where('userid', $userInfo['id'])->first();
      if ($authorBankInfo) {
        return errorJson('AUTHOR_BANK_INFO_CANNOT_CHANGE');
      }
      DB::table('ds_user_bankinfo')->insert([
        'userid' => $userInfo['id'],
        'contactphone' => $request->input('contactphone'),
        'bankuser' => $request->input('bankuser'),
        'bankname' => $request->input('bankname'),
        'bankaccount' => $request->input('bankaccount'),
      ]);
      return successJson();
    }
    /**
     * @router /author/apply/sign
     * @params { id }
     * @response BaseResponse
     * @name 作者申请签约
     */
    public function applySign(Request $request)
    {
        $bookid = $request->input('id') ? $request->input('id') : 0;
        $userInfo = $request->get('userInfo');
        $appliedCache = Redis::get('sign:'.$userInfo['id']);
        if ($appliedCache) {
            return errorInfo('BOOK_HAVE_ALREADY_APPLIED_SIGN');
        }
        if ($bookid == 0) {
            return errorJson('BOOK_NOT_FOUND');
        }
        $book = Book::find($bookid);
        if (!$book && $book->userid != $userInfo['id']) {
            return errorJson('DENY_ALLOW_MANAGE_THIS_BOOK');
        }
        if ($book->issign == 1) {
            return errorJson('BOOK_IS_SIGNED');
        }
        if ($book->bookcnt < BookConfig::ApplySignBookCnt) {
            return errorJson('BOOK_CNT_FEW');
        }
        $signInfo = DB::table('ds_apply_sign')->where('userid', $book->userid)->where('status', 0)->first();
        if ($signInfo) {
            Redis::setex('sign:'.$userInfo['id'], BookConfig::AppliedSignCacheTime, 1);
            return errorInfo('BOOK_HAVE_ALREADY_APPLIED_SIGN');
        }
        DB::table('ds_apply_sign')->insert([
            'userid' => $book->userid,
            'bookid' => $book->id,
            'status' => 0,
            'bookname' => $book->bookname,
            'nickname' => $book->writername,
            'created_at' => date('Y-m-d H:i:s', time()),
            'updated_at' => date('Y-m-d H:i:s', time()),
            'reason' => ''
        ]);
        return successJson();
    }
}
