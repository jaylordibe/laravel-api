<?php

namespace App\Data;

class AddressData extends BaseData
{

    public function __construct(
        public int $userId,
        public string $address,
        public string $villageOrBarangay,
        public string $cityOrMunicipality,
        public string $stateOrProvince,
        public string $zipOrPostalCode,
        public string $country,
        ...$args
    )
    {
        parent::__construct(...$args);
    }

}
