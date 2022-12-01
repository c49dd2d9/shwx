<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Services\GaodeMap;
use App\Enums\RecommendConfig;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use QrCode;
use Illuminate\Support\Facades\DB;
use App\Exceptions\ShanHaiCPException;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redis;

class TestController extends Controller
{
    public function http(Request $request)
    {
      
      $name = '玉龙君';
      $checked =  checkGuestNiackName($name);
      return $checked ? "${name}是游客名称" : "${name}不是游客名称";
      DB::enableQueryLog();
      $page = isset($_GET['page']) ? $_GET['page'] : 1;
        if ($page == 1) {
            $pageOffset = 0;
        } else {
            $pageOffset = ($page * 30) - 1;
        }
      DB::table('ds_pay_recharge')->where('userid', 1)->orderBy('id', 'desc')->select('id', 'userid', 'paymoney', 'orderno', 'thirdorderno', 'specialpaytype', 'payon as status', 'logtime', 'message_info')->paginate(30);
      return DB::getQueryLog();
      $action = $request->input('action');
      if ($request->has('action')) {
        $key = 'baea251d4616df6c82d53447e9719c96';
        $secret = '4731ba49c4e5504819e66cbb7536da76';
        $bookId = 567;
        $chapterId = 0;
        switch ($action) {
          case 'getbooklist':
            // 获取书籍授权列表
            $trueToken = md5(md5($key.$action).$secret);
            break;
          case 'getbookinfo':
            // 获取授权书籍信息
            $trueToken = md5(md5($key.$action.$bookId).$secret);
            break;
          case 'getbookchapterlist':
            // 获取授权书籍章节列表
            $trueToken = md5(md5($key.$action.$bookId).$secret);
            break;
          case 'getbookchapter':
            $trueToken = md5(md5($key.$action.$bookId.$chapterId).$secret);
            break;
          case 'getbookclass':
            $trueToken = md5(md5($key.$action).$secret);
            break;
          default:
            throw new ShanHaiCPException('不允许的接口');
            break;
        }
        return successJson([
          'token' => $trueToken,
          'bookid' => $bookId,
          'key' => $key,
          'secret' => $secret
        ]);
      }

      $platform = $request->input('platform');
      $url = $request->input('url');
      $token = $request->input('token') ? $request->input('token') : '';
      $appid = config('appid.'.$platform);
      $ts = time();
      if ($platform == 'Web') {
        $appid =  $token != '' ? md5($token.$appid) : md5($ts.$appid);
      }
      $nonce = Str::random(15);
      $step_1 = md5($appid);
      $step_2 = md5($step_1.$url.$token.$ts.$platform.$nonce);
      $queryString = 'appid='.$appid.'&nonce='.$nonce.'&platform='.$platform.'&ts='.$ts.'&token='.$token;
      $final_sign = hash('sha256', $step_1.$step_2.md5($nonce).$queryString);
      $data = [
        'url' => $url,
        'token' => $token,
        'appid' => $appid,
        'ts' => $ts,
        'nonce' => $nonce,
        'sign' => $final_sign,
        'query' => '?platform='.$platform.'&ts='.$ts.'&nonce='.$nonce.'&sign='.$final_sign,
      ];
      return successJson($data);
    }
}
