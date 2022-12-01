<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\Notice;
use App\Enums\UserConfig;
use App\Enums\NoticeConfig;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\Controller;

// 公告Controller
class NoticeController extends Controller
{
    /**
     * @router /notice/index/{author}
     * @params {}
     * @response 
     * @name 首页公告
     */
    public function indexList($author)
    {
        if ($author == 1) {
            $noticeKey = 'notice:index:author:index';
            $is_author = 1;
        } else {
            $noticeKey = 'notice:index:index';
            $is_author = 0;
        }
        $notice = Cache::remember($noticeKey, UserConfig::NoticeRedisSecond, function() use ($is_author) {
            return Notice::where('is_author', $is_author)->orderBy('id', 'desc')->limit(NoticeConfig::NoticeIndexCount)->get();
        });
        return successJson($notice);
    }
    /**
     * @router /notice/all/{author}
     * @params {}
     * @response 
     * @name 全部公告
     */
    public function getAllNotice($author)
    {
        $is_author = $author == 1 ? 1 : 0;
        $page = isset($_GET['page']) ? $_GET['page'] : 1;
        $notice = Cache::remember('notice:list_author_page_'.$page, 3600 * 3, function()use($is_author) {
            return Notice::where('is_authhor', $is_author)->orderBy('id', 'desc')->paginate(NoticeConfig::NoticePageLength);
        });
        return successJson($notice);
    }
    /**
     * @router /notice/read/{id}
     * @params {}
     * @response 
     * @name 阅读单条公告
     */
    public function show($id)
    {
        $notice = Notice::find($id);
        if (!$notice) {
            return errorJson('NOTICE_IS_DELETED');
        }
        return successJson($notice);
    }
}
