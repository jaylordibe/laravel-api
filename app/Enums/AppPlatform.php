<?php

namespace App\Enums;

use App\Traits\EnumTrait;

enum AppPlatform: string
{

    use EnumTrait;

    case DESKTOP = 'desktop';
    case MOBILE = 'mobile';
    case WEB = 'web';

}
