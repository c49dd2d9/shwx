<?php
use Illuminate\Support\Facades\Redis;
use App\Enums\UserConfig;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use App\Exceptions\ApiValidatorException;
use App\Exceptions\ShanHaiCPException;
use App\Exceptions\AdminApiException;
use App\Exceptions\ApiPayException;
use App\Enums\UserLoginConfig;

function successJson($data = [])
{
  /**
   * 成功返回
   */
  $successCode = config('responsecode.SUCCESS_RESPONSE');
  return response()->json([
    'code' => $successCode[0],
    'message' => $successCode[1],
    'data' => $data
  ]);
}

function errorJson($errorCode = 'DEFAULT_ERROR', $data = [])
{
  /**
   * 返回Json格式的错误信息
   */
  $errorInfo = config('responsecode.'.$errorCode);
  return response()->json([
    'code' => $errorInfo[0],
    'message' => $errorInfo[1],
    'data' => $data
  ]);
}

function shanhaiErrorJson($message)
{
  /**
   * 返回Json格式的错误信息
   */
  return response()->json([
    'code' => 1,
    'message' => $message,
  ]);
}

function generateShanhaiChannelToken($key, $secret, $action, $token, $data = [])
{
  $trueToken = '';
  switch ($action) {
    case 'getbooklist':
      // 获取书籍授权列表
      $trueToken = md5(md5($key.$action).$secret);
      break;
    case 'getbookinfo':
      // 获取授权书籍信息
      $trueToken = md5(md5($key.$action.$data['bookid']).$secret);
      break;
    case 'getbookchapterlist':
      // 获取授权书籍章节列表
      $trueToken = md5(md5($key.$action.$data['bookid']).$secret);
      break;
    case 'getbookchapter':
      $trueToken = md5(md5($key.$action.$data['bookid'].$data['chapterid']).$secret);
      break;
    case 'getbookclass':
      $trueToken = md5(md5($key.$action).$secret);
      break;
    default:
      throw new ShanHaiCPException('不被允许的接口行为');
      break;
  }
  if ($token != $trueToken) {
    throw new ShanHaiCPException('Token计算错误');
  }
}

function shanhaiSuccessJson($data = [])
{
  return response()->json([
    'code' => 0,
    'messsage' => 'SUCCESS',
    'data' => $data
  ]);
}

function generateToken($nickname, $id)
{
  /**
   * 生成用户 Token
   */
  return sha1($nickname.md5($id));
}

function generateUserToken($userInfo)
{
  /**
   * 生成用户 Token 并写入 redis
   */
  // $token = sha1($userInfo['nickname'].md5($userInfo['id']));
  $token = generateToken($userInfo['nickname'], $userInfo['id']);
  $getToken = unserialize(Redis::get('user:'.$token));
  if ($getToken && time() < $getToken['refresh_time']) {
    $data = [
      'token' => $token,
      'salt' => $getToken['hash']
    ];
    return $data;
  }
  $randSalt = Str::random(UserLoginConfig::UserTokenLength);
  $userInfo['hash'] = $randSalt;
  $userInfo['refresh_time'] = time() + UserConfig::UserTokenRefrshTime;
  Redis::setex('user:'.$token, UserConfig::UserTokenEfficient, serialize($userInfo));
  $data = [
    'token' => $token,
    'salt' => $randSalt
  ];
  return $data;
}


function generateVerifyCode($params)
{
  /**
   * 生成验证码
   */
  $redisPrefix = $params['type'];
  $account = $params['account'];
  $code = rand(111111, 999999);
  $verifyCodeRedisTTL = UserConfig::VerifyCodeEfficientsecond;
  Redis::setex($redisPrefix.':'.$account, $verifyCodeRedisTTL, $code);
  return $code;
}

function generatePassword($password, $passSalt, $new = false)
{
  /**
   * 生成密码
   */
  if ($new) {
    $passSalt = Str::random(6);
    $password = md5(md5($password).$passSalt);
    $data = [
      'password' => $password,
      'salt' => $passSalt,
    ];
  } else {
    if ($passSalt == '' || $passSalt == null) {
      $password = md5($password);
    } else {
      $password = md5(md5($password).$passSalt);
    }
    $data = [
      'password' => $password,
      'salt' => null,
    ];
  }
  return $data;
}

function generateErrorLog($errorName, $message, $params, $router)
{
  /**
   * 生成并写入错误日志，并返回对应的errorId
   */
  $errorId = Str::random(rand(8, 30)).'-'.now();
  if (is_array($params)) {
    $params = implode($params, ',');
  }
  Log::channel('shanhai')->error('errorId:'.$errorId.'['.$errorName.']:'.$message.'|params:'.$params.'|router:'.$router);
  return $errorId;
}

function generatePayErrorLog($message, $money, $aisle)
{
  $errorId = Str::random(rand(8, 30)).'-'.now();
  Log::channel('pay')->info("[errorId]【{$errorId}】 [PayPlatForm]{$aisle} [ErrorMessage]{$message} [PayMoney]{$money}");
  return $errorId;
}

function comment_count_word($str)
{
  /**
   * word方法计算字数
   */
  //$str =characet($str);
  //判断是否存在替换字符
  $is_tihuan_count=substr_count($str,"龘");
  try {
      //先将回车换行符做特殊处理
      $str = preg_replace('/(\r\n+|\s+|　+)/',"龘",$str);
      //处理英文字符数字，连续字母、数字、英文符号视为一个单词
      $str = preg_replace('/[a-z_A-Z0-9-\.!@#\$%\\\^&\*\)\(\+=\{\}\[\]\/",\'<>~`\?:;|]/',"m",$str);
      //合并字符m，连续字母、数字、英文符号视为一个单词
      $str = preg_replace('/m+/',"*",$str);
      //去掉回车换行符
      $str = preg_replace('/龘+/',"",$str);
      //返回字数
      return mb_strlen($str)+$is_tihuan_count;
  } catch (Exception $e) {
      return 0;
  }
}
function validateParams($params, $rules, $message = [])
{
    /**
     * 验证器
     * 参数定义：
     * $params 表单参数
     * $rules 表单规则
     * $message 验证器自定义规则
     */
    $validator = Validator::make($params, $rules, $message);
    if ($validator->fails()) {
      throw new ApiValidatorException($validator->errors()->first());
    }
}

function generateOrderId($user_id, $entrance = 1)
{
  /**
   * $entrance 下单渠道
   * 管理员增加 1
   * 支付宝支付 2
   * 微信支付 3
   * 购买VIP 4
   * 
   */
  $orderRandom = rand(10000, 99999);
  return $entrance.date('Ymd').time().$user_id.$orderRandom;
}

function checkPayPlatform($platform, $money, $app_name)
{
  $allowPayTypeList = [ 'pc', 'wap', 'app' ];
  if (!in_array($platform, $allowPayTypeList)) {
    throw new ApiPayException('PAY_CHANNEL_ILLEGAL', '[用户输入]'.$money, $app_name);
  }
}

function checkSignAuthorInfoValueisNull($value)
{
  if ($value == null || $value == '') {
    throw new AdminApiException('AUTHOR_INFO_IMPERFECT');
  }
}

function checkBanExpiredTimeInAllowList($time)
{
  $allowexpiredTimeList = [ 86400, 259200, 604800, 2592000, 15552000, 31536000 ];
  if (!in_array($time, $allowexpiredTimeList)) {
    throw new AdminApiException('THIS_TIMESTAMP_NOT_IN_BAN_TIME_ALLOW_LIST');
  }
}

function checkGuestNiackName($name) {
  if (preg_match("/U[0-9]{1,9}/i", $name) == 0) {
    return false;
  } else {
    return true;
  }
}

