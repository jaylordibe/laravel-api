<?php

namespace App\Enums;

enum PasswordResetStatus: string
{

    case INITIATED = 'initiated';
    case USED = 'used';

}
