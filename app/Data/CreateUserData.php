<?php

namespace App\Data;

use App\Enums\UserRole;
use Spatie\LaravelData\Data;

class CreateUserData extends Data
{

    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $phoneNumber,
        public string $password,
        public UserRole $role
    )
    {
    }

}
