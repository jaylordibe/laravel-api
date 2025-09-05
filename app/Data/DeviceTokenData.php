<?php

namespace App\Data;

use App\Enums\AppPlatform;
use App\Enums\DeviceOs;
use App\Enums\DeviceType;

class DeviceTokenData extends BaseData
{

    public function __construct(
        public int $userId,
        public string $token,
        public AppPlatform $appPlatform,
        public DeviceType $deviceType,
        public DeviceOs $deviceOs,
        public string $deviceOsVersion,
        ...$args
    )
    {
        parent::__construct(...$args);
    }

}
