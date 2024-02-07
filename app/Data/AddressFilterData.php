<?php

namespace App\Data;

class AddressFilterData extends BaseData
{

    public function __construct(
        public ?int $userId = null,
        public ?string $address = null,
        public ?string $villageOrBarangay = null,
        public ?string $cityOrMunicipality = null,
        public ?string $stateOrProvince = null,
        public ?string $zipOrPostalCode = null,
        public ?string $country = null,
        ...$args
    )
    {
        parent::__construct(...$args);
    }

}
