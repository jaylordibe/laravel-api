<?php

namespace App\Enums;

use App\Traits\EnumTrait;

enum DeviceType: string
{

    use EnumTrait;

    case SMARTPHONE = 'smartphone';
    case SMARTWATCH = 'smartwatch';
    case TABLET = 'tablet';
    case LAPTOP = 'laptop';
    case DESKTOP = 'desktop';

}
