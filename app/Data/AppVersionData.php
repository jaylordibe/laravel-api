<?php

namespace App\Data;

use Illuminate\Support\Carbon;

class AppVersionData extends BaseData
{

    public function __construct(
        public string $version,
        public ?string $description,
        public string $platform,
        public Carbon $releaseDate,
        public ?string $downloadUrl,
        public bool $forceUpdate,
        ...$args
    )
    {
        parent::__construct(...$args);
    }

}
