<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class SignUpUserData extends Data
{

    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $phoneNumber,
        public string $rawPassword,
        public string $rawPasswordConfirmation
    )
    {
    }

}
