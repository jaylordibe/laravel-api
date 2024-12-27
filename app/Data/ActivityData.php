<?php

namespace App\Data;

class ActivityData extends BaseData
{

    public function __construct(
        public int $userId,
        public string $type,
        public string $description,
        public array $properties = [],
        ...$args
    )
    {
        parent::__construct(...$args);
    }

}
