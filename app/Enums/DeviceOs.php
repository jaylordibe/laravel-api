<?php

namespace App\Enums;

use App\Traits\EnumTrait;

enum DeviceOs: string
{

    use EnumTrait;

    case IOS = 'ios';
    case ANDROID = 'android';
    case MACOS = 'macos';
    case LINUX = 'linux';
    case WINDOWS = 'windows';

}
