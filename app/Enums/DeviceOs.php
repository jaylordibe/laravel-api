<?php

namespace App\Enums;

enum DeviceOs: string
{

    case IOS = 'ios';
    case ANDROID = 'android';
    case MACOS = 'macos';
    case LINUX = 'linux';
    case WINDOWS = 'windows';

}
