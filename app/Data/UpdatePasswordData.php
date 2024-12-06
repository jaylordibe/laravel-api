<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class UpdatePasswordData extends Data
{

    public function __construct(
        public int $userId,
        public string $password,
        public string $passwordConfirmation
    )
    {
    }

}
