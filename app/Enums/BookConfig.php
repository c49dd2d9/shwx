<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static OptionOne()
 * @method static static OptionTwo()
 * @method static static OptionThree()
 */
final class BookConfig extends Enum
{
    const BookTagMaxCount = 6; // 每本书Tag最大数量
    const AppliedSignCacheTime = 3 * 24 * 3600; // 申请签约缓存时间
    const ApplySignBookCnt = 10000; // 申请签约时需要多少字数
    const BookNormalPublishstatus = 1; // 默认发表状态
    const ReaderBookCacheKey = 'reader:book:'; // 读者端作品缓存Key
    const ReaderBookCacheEffective = 1800; // 读者端作品缓存有效时间
    const ReaderChapterCacheKey = 'reader:chapter:list:'; // 读者端作品章节缓存Key
    const ReaderBookTagCacheKey = 'reader:booktag:';
    const ReaderBookSectionCacheKey = 'reader:book:section:';
    const ReaderBookTagInfoCacheKey = 'reader:booktag:show:';
    const ReaderBookExtendInfoCacheKey = 'reader:bookextend:';
    const EditorUserId = 505;
    const BuyVipChapterUnitPrice = 5;
}
