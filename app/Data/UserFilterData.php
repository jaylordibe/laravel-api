<?php

namespace App\Data;

class UserFilterData extends BaseData
{

    public function __construct(
        public ?array $roles = null,
        public ?array $permissions = null,
        ...$args
    )
    {
        parent::__construct(...$args);
    }

}
