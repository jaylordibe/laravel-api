<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class UpdateEmailData extends Data
{

    public function __construct(
        public int $userId,
        public string $email,
        public string $currentPassword
    )
    {
    }

}
