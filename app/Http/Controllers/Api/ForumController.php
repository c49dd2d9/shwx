<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Enums\BoardConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\Controller;

// 论坛（板块）Controller
class ForumController extends Controller
{
     /**
     * @router /board/list
     * @params {}
     * @response { 'id', 'name', 'introduction', 'status' }
     * @name 版块列表
     */
    public function list()
    {
        $board = Cache::remember(BoardConfig::BoardRedisKey, BoardConfig::BoardCachesecond, function() {
            return DB::table(BoardConfig::BoardTableName)->select('id', 'name', 'introduction', 'status')->get();
        });
        return successJson([ 'list' => $board ]);
    }
}
