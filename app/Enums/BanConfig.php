<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static OptionOne()
 * @method static static OptionTwo()
 * @method static static OptionThree()
 */
final class BanConfig extends Enum
{
    const BanComment = 1;
    const BanBoard = 2;
}
