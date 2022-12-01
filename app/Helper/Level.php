<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Exceptions\AdminApiException;


function setLevelCache()
{
  $data = Cache::remember('user:level:config:list', 300, function() {
    // return DB::table('ds_user_group_role')->get();
    return DB::table('ds_user_info')->get();
  });
  return $data;
}

function getLevelInfo($level = 1)
{
  // 获取当前等级缓存
  $dataAll = setLevelCache();
  $levelData = isset($dataAll[$level]) ? $dataAll[$level] : null;
  if ($levelData == null) {
    throw new AdminApiException('USER_GROUP_NOT_FOUND');
  }
  return $levelData;
}


