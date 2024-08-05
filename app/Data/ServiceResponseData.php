<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class ServiceResponseData extends Data
{

    public function __construct(
        public bool $success = false,
        public string $message = '',
        public mixed $data = null,
        public int $statusCode = 0,
    )
    {
    }

    /**
     * Check if the response is successful.
     *
     * @return bool
     */
    public function successful(): bool
    {
        return $this->success;
    }

    /**
     * Check if the response is failed.
     *
     * @return bool
     */
    public function failed(): bool
    {
        return !$this->success;
    }

}
