<?php

namespace App\Data;

use App\Enums\AppPlatform;
use Illuminate\Support\Carbon;

class AppVersionData extends BaseData
{

    public function __construct(
        public string $version,
        public ?string $description,
        public AppPlatform $platform,
        public Carbon $releaseDate,
        public ?string $downloadUrl,
        public bool $forceUpdate,
        ...$args
    )
    {
        parent::__construct(...$args);
    }

}
