<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static OptionOne()
 * @method static static OptionTwo()
 * @method static static OptionThree()
 */
final class NoticeConfig extends Enum
{
    const NoticeIndexCount = 10; // 首页公告显示多少？
    const NoticePageLength = 30; // 公告列表页每页的Item数量
}
