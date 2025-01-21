<?php

namespace App\Enums;

enum UserRole: string
{

    case SYSTEM_ADMIN = 'system_admin';
    case APP_ADMIN = 'app_admin';

}
