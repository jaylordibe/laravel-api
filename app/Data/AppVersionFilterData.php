<?php

namespace App\Data;

use Illuminate\Support\Carbon;

class AppVersionFilterData extends BaseData
{

    public function __construct(
        public ?string $version = null,
        public ?string $platform = null,
        public ?Carbon $releaseDateStart = null,
        public ?Carbon $releaseDateEnd = null,
        public ?bool $forceUpdate = null,
        ...$args
    )
    {
        parent::__construct(...$args);
    }

}
