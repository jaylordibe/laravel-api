<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class ResetPasswordData extends Data
{

    public function __construct(
        public string $email,
        public string $token,
        public string $password
    )
    {
    }

}
