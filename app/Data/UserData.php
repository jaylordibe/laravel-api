<?php

namespace App\Data;

use Illuminate\Support\Carbon;

class UserData extends BaseData
{

    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $username,
        public string $email,
        public ?string $middleName = null,
        public ?string $timezone = null,
        public ?string $phoneNumber = null,
        public ?Carbon $birthday = null,
        public ?string $profilePicture = null,
        public ?array $roles = null,
        public ?array $permissions = null,
        ...$args
    )
    {
        parent::__construct(...$args);
    }

}
