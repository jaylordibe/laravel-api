<?php

namespace App\Data;

use Illuminate\Support\Carbon;

class UserFilterData extends BaseData
{

    public function __construct(
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $username = null,
        public ?string $email = null,
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
