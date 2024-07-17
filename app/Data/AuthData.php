<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class AuthData extends Data
{

    public function __construct(
        public string $identifier,
        public string $password,
        public bool $remember
    )
    {
    }

}
