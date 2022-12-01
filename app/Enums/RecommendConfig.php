<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static OptionOne()
 * @method static static OptionTwo()
 * @method static static OptionThree()
 */
final class RecommendConfig extends Enum
{
    /**
     * Index Recommend Config
     */
    const IndexLastUpdate = array('id' => 1, 'count' => 4); // 首页最近更新
    const IndexBannerPic = array('id' => 2, 'count' => 6); // 首页轮播图
    const IndexBoutique = array('id' => 3, 'count' => 6); // 首页精品力荐
    const IndexHotRecommend = array('id' => 4, 'count' => 3); //首页火热推荐
    const IndexEditorRecommend = array('id' => 5, 'count' => 1); //首页主编推荐
    const IndexNewBook = array('id' => 6, 'count' => 3); // 首页新书精选
    const IndexDiligentUpdate = array('id' => 7, 'count' => 3); // 首页勤更专区
    const IndexEndBook = array('id' => 8, 'count' => 3); // 首页完结精选
    /**
     * Category Recommend Config[言情]
     */
    const CategoryBannerPicYQ = array('id' => 9, 'count' => 6); // 频道页轮播图
    const CategoryBoutiqueYQ = array('id' => 10, 'count' => 6); // 频道页精品力荐
    const CategoryEditorRecommendYQ = array('id' => 11, 'count' => 1); // 频道页主编力荐
    const CategoryClassRecommendYQ = array('id' => 12, 'count' => 3); //频道页分类专区
    const CategoryMinorityYQ = array('id' => 13, 'count' => 3); // 频道页小众安利
    const CategoryNewBookYQ = array('id' => 14, 'count' => 6); // 频道页新书精选
    const CategoryHotRecommendYQ = array('id' => 15, 'count' => 6); // 频道页火热推荐
    const CategoryCapriceRecommendYQ = array('id' => 16, 'count' => 3); // 频道页任性推荐
    const CategoryEndBookYQ = array('id' => 17, 'count' => 6); //频道页完结精选
    const CategoryLastUpdateYQ = array('id' => 18, 'count' => 4);// 频道页最近更新
    const CategoryWeekRecommendYQ = array('id' => 19, 'count' => 8); // 频道页本周强推
    /**
     * Category Recommend Config[纯爱]
     */
    const CategoryBannerPicCA = array('id' => 20, 'count' => 6); // 频道页轮播图
    const CategoryBoutiqueCA = array('id' => 21, 'count' => 6); // 频道页精品力荐
    const CategoryEditorRecommendCA = array('id' => 22, 'count' => 1); // 频道页主编力荐
    const CategoryClassRecommendCA = array('id' => 23, 'count' => 3); //频道页分类专区
    const CategoryMinorityCA = array('id' => 24, 'count' => 3); // 频道页小众安利
    const CategoryNewBookCA = array('id' => 25, 'count' => 6); // 频道页新书精选
    const CategoryHotRecommendCA = array('id' => 26, 'count' => 6); // 频道页火热推荐
    const CategoryCapriceRecommendCA = array('id' => 27, 'count' => 3); // 频道页任性推荐
    const CategoryEndBookCA = array('id' => 28, 'count' => 6); //频道页完结精选
    const CategoryLastUpdateCA = array('id' => 29, 'count' => 4);// 频道页最近更新
    const CategoryWeekRecommendCA = array('id' => 30, 'count' => 8); // 频道页本周强推
    /**
     * Category Recommend Config[其它]
     */
    const CategoryBannerPicOther = array('id' => 31, 'count' => 6); // 频道页轮播图
    const CategoryBoutiqueOther = array('id' => 32, 'count' => 6); // 频道页精品力荐
    const CategoryEditorRecommendOther = array('id' => 33, 'count' => 1); // 频道页主编力荐
    const CategoryClassRecommendOther = array('id' => 34, 'count' => 3); //频道页分类专区
    const CategoryMinorityOther = array('id' => 35, 'count' => 3); // 频道页小众安利
    const CategoryNewBookOther = array('id' => 36, 'count' => 6); // 频道页新书精选
    const CategoryHotRecommendOther = array('id' => 37, 'count' => 6); // 频道页火热推荐
    const CategoryCapriceRecommendOther = array('id' => 38, 'count' => 3); // 频道页任性推荐
    const CategoryEndBookOther = array('id' => 39, 'count' => 6); //频道页完结精选
    const CategoryLastUpdateOther = array('id' => 40, 'count' => 4);// 频道页最近更新
    const CategoryWeekRecommendOther = array('id' => 41, 'count' => 8); // 频道页本周强推
    /**
     * PC Banner Recommend Config
     */
    const PCIndexBannerPic = array('id' => 42, 'count' => 6); // PC轮播图
    const PCCategoryBannerPicYQ = array('id' => 43, 'count' => 6); // PC轮播图(言情)
    const PCCategoryBannerPicCA = array('id' => 44, 'count' => 6); // PC轮播图（纯爱）
    const PCCategoryBannerPicOther = array('id' => 45, 'count' =>6); // PC轮播图（其他）
}
