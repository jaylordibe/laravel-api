<?php

namespace App\Data;

class DeviceTokenData extends BaseData
{

    public function __construct(
        public int $userId,
        public string $token,
        public string $appPlatform,
        public string $deviceType,
        public string $deviceOs,
        public string $deviceOsVersion,
        ...$args
    )
    {
        parent::__construct(...$args);
    }

}
