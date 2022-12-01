<?php
namespace App\Http\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Redis;
use Exception;

class GaodeMap
{
  private static $baseUrl = 'http://restapi.amap.com/v3';
  
  private static $redisPrefix = 'gaode:';

  static private function getApiResponse($method, $url, $query)
  {
    $client = new Client();
    $response = $client->request($method, $url.$query);
    if ($response->getStatusCode() == 200) {
      $data = json_decode($response->getBody());
      if ($data->status == 0) {
        throw new Exception('API调用失败,原因是:'.$data->info);
      }
      return $data;
    } else {
      throw new Exception('调用高德地图接口失败，错误代码:'.$response->getStatusCode().',URL:'.$url.',Params:'.$query);
    }
  }
  static public function getCityCode($address)
  {
    // 获取城市Code
    $cityCodeCacheInvalid = 24 * 60 * 60; // 由于citycode是不变的，而且是可以共用的，所以失效时间为24小时
    $cityCodeCachekey = self::$redisPrefix.'citycode:'.md5($address); // 由于address参数是中文，因此md5加密一下
    $cityCodeCacheInfo = Redis::get($cityCodeCachekey);
    if ($cityCodeCacheInfo) {
      return unserialize($cityCodeCacheInfo);
    }
    $apiUrl = self::$baseUrl.'/geocode/geo';
    $urlParams = '?key='.config('gaode.app_key').'&address='.$address;
    $client = GaodeMap::getApiResponse('GET', $apiUrl, $urlParams);
    Redis::setex($cityCodeCachekey, $cityCodeCacheInvalid, serialize($client->geocodes[0]->adcode));
    return $client->geocodes[0]->adcode ? $client->geocodes[0]->adcode : 0;
  }
  static public function getUserIpInfo($ip)
  {
    // 根据用户IP，获取IP定位等信息
    $userIpData = [
      'province' => '', // String,
      'city' => '', // String,
      'adcode' => 0, // Int
    ];
    $userIpCacheInvalid = 10800; // 失效时间为3小时
    $redisIpInfo = str_replace('.', '', $ip);
    $userIpCacheKey = self::$redisPrefix.'uip:'.$redisIpInfo;
    $userIpCacheInfo = Redis::get($userIpCacheKey);
    if ($userIpCacheInfo) {
      return unserialize($userIpCacheInfo);
    }
    $apiUrl = self::$baseUrl.'/ip';
    $urlParams = '?key='.config('gaode.app_key').'&ip='.$ip;
    $client = GaodeMap::getApiResponse('GET', $apiUrl, $urlParams);
    if (!is_array($client->adcode)) {
      $userIpData = [
        'province' => $client->province,
        'city' => $client->city,
        'adcode' => intval($client->adcode),
      ];
    }
    Redis::setex($userIpCacheKey, $userIpCacheInvalid, serialize($userIpData));
    return $userIpData;
  }
  static public function getWeatherInfo($cityCode)
  {
    // 根据城市Code获取天气信息
    $weatherData = [
      'province' => '', // String
      'city' => '', // String
      'weather' => '', // String
      'temperature' => 0, // Int
      'winddirection' => '', // String
    ];
    if (!$cityCode) {
      // 如果没有cityCode, 则直接返回
      return $weatherData;
    }
    $weatherCacheInvalid = 10800; // 失效时间为3小时
    $weatherCacheKey = self::$redisPrefix.'weather:'.$cityCode;
    $weatherCacheInfo = Redis::get($weatherCacheKey);
    if ($weatherCacheInfo) {
      // 如果有缓存直接返回缓存，避免再次调用
      return unserialize($weatherCacheInfo);
    }
    $apiUrl = self::$baseUrl.'/weather/weatherInfo';
    $urlParams = '?key='.config('gaode.app_key').'&city='.$cityCode.'&extensions=base';
    $client = GaodeMap::getApiResponse('GET', $apiUrl, $urlParams);
    if ($client->count != 0) {
      $weatherData = [
        'province' => $client->lives[0]->province,
        'city' => $client->lives[0]->city,
        'weather' => $client->lives[0]->weather,
        'temperature' => intval($client->lives[0]->temperature),
        'winddirection' => $client->lives[0]->winddirection,
      ];
    }
    Redis::setex($weatherCacheKey, $weatherCacheInvalid, serialize($weatherData));
    return $weatherData;
  }
}