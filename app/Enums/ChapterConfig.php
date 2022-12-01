<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static OptionOne()
 * @method static static OptionTwo()
 * @method static static OptionThree()
 */
final class ChapterConfig extends Enum
{
    const ChapterNormalPublishstatus = 1; // 章节默认发表状态
    const DirectPublishStatus = 1; // 直接发布书籍而非放入存稿箱
}
