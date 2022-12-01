<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static OptionOne()
 * @method static static OptionTwo()
 * @method static static OptionThree()
 */
final class UserLoginConfig extends Enum
{
    const Android = '安卓APP'; // 不解释了。。。
    const iOS = '苹果APP';// 不解释了。。。
    const Web = '网页端';// 这还需要解释？
    const UserTokenLength = 10;
}
