<?php

namespace App\Enums;

use App\Traits\EnumTrait;

enum Gender: string
{

    use EnumTrait;

    case MALE = 'male';
    case FEMALE = 'female';

}
