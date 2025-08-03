<?php

namespace App\Enums;

use App\Traits\EnumTrait;

enum UserRole: string
{

    use EnumTrait;

    case SYSTEM_ADMIN = 'system_admin';
    case APP_ADMIN = 'app_admin';

}
