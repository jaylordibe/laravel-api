<?php

namespace App\Data;

class ActivityData extends BaseData
{

    public function __construct(
        public int $userId,
        public string $logName,
        public string $description,
        public array $properties = [],
        ...$args
    )
    {
        parent::__construct(...$args);
    }

}
