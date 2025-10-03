<?php

namespace App\Data;

use App\Enums\Gender;
use Illuminate\Support\Carbon;

class UserData extends BaseData
{

    public function __construct(
        public string $firstName,
        public ?string $middleName,
        public string $lastName,
        public string $username,
        public string $email,
        public ?Carbon $emailVerifiedAt,
        public ?string $phoneNumber,
        public ?Gender $gender,
        public ?Carbon $birthdate,
        public ?string $timezone,
        public ?string $profileImage,
        public ?array $roles = null,
        public ?array $permissions = null,
        ...$args
    )
    {
        parent::__construct(...$args);
    }

}
