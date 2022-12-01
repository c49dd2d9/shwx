<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });
Route::post('/app/test', 'Api\TestController@http'); 
Route::post('/user/login', 'Api\UserController@login'); // 已测试
Route::post('/user/register', 'Api\UserController@register'); 
Route::post('/user/send/phone/verifycode', 'Api\UserController@sendPhoneVerifyCode'); // 已测试
Route::post('/user/send/email/verifycode', 'Api\UserController@sendEmailVerifyCode');
Route::post('/user/change/password', 'Api\UserController@changePassword'); // 已测试
Route::post('/user/change/login/account', 'Api\UserController@updateEmailOrPhone')->middleware('user.auth'); // 已测试
Route::post('/user/change/info', 'Api\UserController@changeUserInfo')->middleware('user.auth');
Route::get('/get/user/info', 'Api\UserController@getUserInfo')->middleware('user.auth');
Route::post('/board/list', 'Api\ForumController@list');
Route::post('/create/topic', 'Api\TopicController@create')->middleware('user.auth', 'check.banuser');
Route::post('/topic/list', 'Api\TopicController@list');
Route::post('/create/reply', 'Api\ReplyController@create')->middleware('user.auth', 'check.banuser');
Route::post('/topic/show', 'Api\TopicController@show')->middleware('user.auth');
Route::post('/admin/create/notice', 'Api\AdminController@createNotice');
Route::post('/admin/get/notice/list', 'Api\AdminController@getNoticeList');
Route::post('/admin/get/notice/info', 'Api\AdminController@getNoticeInfo');
Route::post('/admin/update/notice/info', 'Api\AdminController@updateNoticeInfo');
Route::post('/admin/delete/notice', 'Api\AdminController@deleteNotice');
Route::post('/admin/create/notify', 'Api\Admin\NotifyController@create');
Route::post('/admin/delete/notify', 'Api\Admin\NotifyController@deleteNotify');
Route::post('/admin/get/notify/list', 'Api\Admin\NotifyController@getNotifyList');
Route::post('/admin/change/notify/info', 'Api\Admin\NotifyController@changeNotifyInfo');
Route::get('/notice/index/{author}', 'Api\NoticeController@indexList');
Route::get('/notice/all/{author}', 'Api\NoticeController@getAllNotice');
Route::get('/notice/read/{id}', 'Api\NoticeController@show');
Route::post('/create/book', 'Api\BookController@create')->middleware('user.auth');
Route::post('/author/get/book', 'Api\BookController@authorGetBookInfo')->middleware('user.auth');
Route::post('/update/book/info', 'Api\BookController@update')->middleware('user.auth');
Route::post('/author/apply/sign', 'Api\AuthorController@applySign')->middleware('user.auth');
Route::post('/create/section', 'Api\BookController@createSection')->middleware('user.auth');
Route::post('/get/section/info', 'Api\BookController@getSectionInfo')->middleware('user.auth');
Route::post('/change/section', 'Api\BookController@changeSection')->middleware('user.auth');
Route::post('/delete/section', 'Api\BookController@deleteSection')->middleware('user.auth');
Route::post('/create/comment', 'Api\CommentController@create')->middleware('user.auth', 'check.banuser');
Route::post('/get/book/comment', 'Api\CommentController@getBookComment');
Route::post('/get/chapter/comment', 'Api\CommentController@getChapterComment');
Route::post('/user/signin', 'Api\UserController@signIn')->middleware('user.auth');
Route::post('/admin/add/book/recommend', 'Api\AdminController@addRecommend')->middleware('user.auth');
Route::get('/get/index/recommend', 'Api\RecommendController@index');
Route::post('/change/author/info', 'Api\AuthorController@changeAuthorInfo')->middleware('user.auth', 'author');
Route::post('/create/author/pay/info', 'Api\AuthorController@createAuthorPayInfo')->middleware('user.auth', 'author');
Route::post('/admin/change/user/gold', 'Api\Admin\UserController@changeUserGold');
Route::get('/get/pay/program', 'Api\PayController@getPayProgram')->middleware('user.auth');
Route::post('/buy/gold/alipay', 'Api\PayController@aliPay')->middleware('user.auth');
Route::post('/buy/gold/wechatpay', 'Api\PayController@wechatPay')->middleware('user.auth');
Route::post('/alipay/notify', 'Api\PayController@aliPayNotify');
Route::post('/wechat/pay/notify', 'Api\PayController@wechatPayNotify');
Route::get('/get/user/order/list', 'Api\UserController@getUserOrder')->middleware('user.auth');
Route::get('/get/book/info/{bookid}', 'Api\ChapterController@getChapterList');
Route::post('/read/chapter', 'Api\ChapterController@getChapterInfo')->middleware('user.auth');
Route::post('/upload/image', 'Api\UploadController@uploadImage')->middleware('user.auth');
Route::post('/admin/upload/image', 'Api\UploadController@adminUploadFile');
Route::post('/get/unread/new/notify', 'Api\NotifyController@getUnreadNotifyCount')->middleware('user.auth');
Route::get('/get/notify/list', 'Api\NotifyController@getNotifyList')->middleware('user.auth');
Route::post('/user/phone/verify/code/login', 'Api\UserController@phoneVerifyCodeLogin');
Route::get('/book/update/calendar/{bookid}', 'Api\BookController@updateCalendar');