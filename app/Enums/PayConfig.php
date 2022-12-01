<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static OptionOne()
 * @method static static OptionTwo()
 * @method static static OptionThree()
 */
final class PayConfig extends Enum
{
    const PayList = array(1, 2, 3, 4);
    const PayCacheKey = 'pay:program:list';
}
