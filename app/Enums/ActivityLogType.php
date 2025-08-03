<?php

namespace App\Enums;

use App\Traits\EnumTrait;

enum ActivityLogType: string
{

    use EnumTrait;

    case LOGIN = 'login';

}
