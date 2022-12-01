<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use App\Enums\RecommendConfig;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// 推荐Controller
class RecommendController extends Controller
{
    public function getRecommendId($moduleName, $list)
    {
        $redisKey = 'recommend:id:list:'.$moduleName;
        $redisInfo = Redis::get($redisKey);
        if ($redisInfo) {
            return unserialize($redisInfo);
        }
        $listInfo = [];
        $idList = [];
        foreach($list as $item) {
            array_push($listInfo, RecommendConfig::getValue($item));
        }
        foreach($listInfo as $ListInfoItem) {
            array_push($idList, $ListInfoItem['id']);
        }
        Redis::setex($redisKey, 24 * 3600, serialize($idList));
        return $idList;
    }
    public function index()
    {
        /**
         * 这里的四个API都是一样的
         * $recommendNameList 数据来源是 RecommendConfig 相应的 Key
         * 所以说如果要动榜单的话，就需要动2个文件
         * $getRecommendIdList 调用本 Controller 的 getRecommendId 方法，遍历获得所有ID，并将其保存在 redis 中
         * $recommendData  从数据库中取得对应榜单的所有内容，如果没有缓存的话，需要将其保存在 Cache 里，每 24小时 刷新一次，前端依照 recommendid 这个字段自行去过滤对应的信息……、
         * 不过其实前端还是我，丢人QWQ
         * 好累啊，今天这个B班就上到这里吧
         */
        $recommendNameList = [ 'IndexLastUpdate', 'IndexBoutique', 'IndexHotRecommend', 'IndexEditorRecommend', 'IndexNewBook', 'IndexDiligentUpdate', 'IndexEndBook' ];
        $getRecommendIdList = $this->getRecommendId('index', $recommendNameList);
        $recommendData = Cache::remember('recommend:index', 24 * 3600, function() use($getRecommendIdList){
            return DB::table('ds_book_recommend')->whereIn('recommendid', $getRecommendIdList)->get();
        });
        return successJson($recommendData);
    }
    public function categoryYanqing()
    {
        $recommendNameList = [ 'CategoryBoutiqueYQ', 'CategoryEditorRecommendYQ', 'CategoryClassRecommendYQ', 'CategoryMinorityYQ', 'CategoryNewBookYQ', 'CategoryHotRecommendYQ', 'CategoryCapriceRecommendYQ', 'CategoryEndBookYQ', 'CategoryLastUpdateYQ', 'CategoryWeekRecommendYQ' ];
        $getRecommendIdList = $this->getRecommendId('yanqing', $recommendNameList);
        $recommendData = Cache::remember('recommend:yanqing', 24 * 3600, function() use($getRecommendIdList){
            return DB::table('ds_book_recommend')->whereIn('recommendid', $getRecommendIdList)->get();
        });
        return successJson($recommendData);
    }
    public function categoryChunai()
    {
        $recommendNameList = [ 'CategoryBoutiqueCA', 'CategoryEditorRecommendCA', 'CategoryClassRecommendCA', 'CategoryMinorityCA', 'CategoryNewBookCA', 'CategoryHotRecommendCA', 'CategoryCapriceRecommendCA', 'CategoryEndBookCA', 'CategoryLastUpdateCA', 'CategoryWeekRecommendCA' ];
        $getRecommendIdList = $this->getRecommendId('chunai', $recommendNameList);
        $recommendData = Cache::remember('recommend:yanqing', 24 * 3600, function() use($getRecommendIdList){
            return DB::table('ds_book_recommend')->whereIn('recommendid', $getRecommendIdList)->get();
        });
        return successJson($recommendData);
    }
    public function categoryOther()
    {
        $recommendNameList = ['CategoryBoutiqueOther', 'CategoryEditorRecommendOther', 'CategoryClassRecommendOther', 'CategoryMinorityOther', 'CategoryNewBookOther', 'CategoryHotRecommendOther', 'CategoryCapriceRecommendOther', 'CategoryEndBookOther', 'CategoryLastUpdateOther', 'CategoryWeekRecommendOther' ];
        $getRecommendIdList = $this->getRecommendId('other', $recommendNameList);
        $recommendData = Cache::remember('recommend:yanqing', 24 * 3600, function() use($getRecommendIdList){
            return DB::table('ds_book_recommend')->whereIn('recommendid', $getRecommendIdList)->get();
        });
        return successJson($recommendData);
    }
    public function categoryBanner($index, $platform)
    {
        $getautoplayDelayConfig = Redis::get('banner:autopay_delay');
        $getautoplayDelay = $getautoplayDelayConfig ? $getautoplayDelayConfig : 2000;
        switch ($index) {
            case 'index':
                $recommendInfo = $platform == 'pc' ? RecommendConfig::PCIndexBannerPic : RecommendConfig::IndexBannerPic;
                break;
            case 'yanqing': 
                $recommendInfo = $platform == 'pc' ? RecommendConfig::PCCategoryBannerPicYQ : RecommendConfig::CategoryBannerPicYQ;
                break;
            case 'chunai': 
                $recommendInfo = $platform == 'pc' ? RecommendConfig::PCCategoryBannerPicCA : RecommendConfig::CategoryBannerPicCA;
                break;
            case 'other':
                $recommendInfo = $platform == 'pc' ? RecommendConfig::PCCategoryBannerPicOther : RecommendConfig::CategoryBannerPicOther;
                break;
            default:
                return errorJson();
                break;
        }
        $recommendData = Cache::remember('recoomed:banner:'.$recommendInfo['id'], 24 * 3600, function() use($recommendInfo){
            return DB::table('ds_book_recommend')->whereIn('recommendid', $recommendInfo['id'])->get();
        });
        $data = [
            'recommend_data' => $recommendData,
            'autoplay_delay' => $getautoplayDelay,
        ];
        return successJson($data);
    }
}
