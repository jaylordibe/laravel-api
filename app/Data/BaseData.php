<?php

namespace App\Data;

use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class BaseData extends Data
{

    public function __construct(
        public ?int $id = null,
        public ?Carbon $createdAt = null,
        public ?Carbon $updatedAt = null,
        public ?Carbon $deletedAt = null,
        public ?int $createdBy = null,
        public ?int $updatedBy = null,
        public ?int $deletedBy = null,
        public ?UserData $authUser = null,
        public ?MetaData $meta = null
    )
    {
    }

}
