<?php

namespace App\Data;

use App\Enums\AppPlatform;
use App\Enums\DeviceOs;
use App\Enums\DeviceType;

class DeviceTokenFilterData extends BaseData
{

    public function __construct(
        public ?int $userId = null,
        public ?AppPlatform $appPlatform = null,
        public ?DeviceType $deviceType = null,
        public ?DeviceOs $deviceOs = null,
        public ?string $deviceOsVersion = null,
        ...$args
    )
    {
        parent::__construct(...$args);
    }

}
