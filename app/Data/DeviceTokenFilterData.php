<?php

namespace App\Data;

class DeviceTokenFilterData extends BaseData
{

    public function __construct(
        public ?int $userId = null,
        public ?string $appPlatform = null,
        public ?string $deviceType = null,
        public ?string $deviceOs = null,
        public ?string $deviceOsVersion = null,
        ...$args
    )
    {
        parent::__construct(...$args);
    }

}
