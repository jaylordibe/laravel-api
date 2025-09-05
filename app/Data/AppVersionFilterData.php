<?php

namespace App\Data;

use App\Enums\AppPlatform;
use Illuminate\Support\Carbon;

class AppVersionFilterData extends BaseData
{

    public function __construct(
        public ?string $version = null,
        public ?AppPlatform $platform = null,
        public ?Carbon $releaseDateStart = null,
        public ?Carbon $releaseDateEnd = null,
        public ?bool $forceUpdate = null,
        ...$args
    )
    {
        parent::__construct(...$args);
    }

}
