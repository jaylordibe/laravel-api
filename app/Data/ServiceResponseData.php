<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class ServiceResponseData extends Data
{

    public function __construct(
        public bool $success = false,
        public bool $error = false,
        public int $statusCode = 0,
        public string $message = '',
        public array|object|null $data = null
    )
    {
    }

}
