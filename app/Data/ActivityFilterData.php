<?php

namespace App\Data;

use Illuminate\Support\Carbon;

class ActivityFilterData extends BaseData
{

    public function __construct(
        public ?int $userId = null,
        public ?string $type = null,
        public ?Carbon $startDate = null,
        public ?Carbon $endDate = null,
        ...$args
    )
    {
        parent::__construct(...$args);
    }

}
