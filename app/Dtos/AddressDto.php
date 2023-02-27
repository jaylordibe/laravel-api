<?php

namespace App\Dtos;

class AddressDto extends BaseDto
{

    private ?int $userId = null;
    private ?string $address = null;
    private ?string $villageOrBarangay = null;
    private ?string $cityOrMunicipality = null;
    private ?string $stateOrProvince = null;
    private ?string $zipOrPostalCode = null;
    private ?string $country = null;

    /**
     * @return int|null
     */
    public function getUserId(): ?int
    {
        return $this->userId;
    }

    /**
     * @param int|null $userId
     */
    public function setUserId(?int $userId): void
    {
        $this->userId = $userId;
    }

    /**
     * @return string|null
     */
    public function getAddress(): ?string
    {
        return $this->address;
    }

    /**
     * @param string|null $address
     */
    public function setAddress(?string $address): void
    {
        $this->address = $address;
    }

    /**
     * @return string|null
     */
    public function getVillageOrBarangay(): ?string
    {
        return $this->villageOrBarangay;
    }

    /**
     * @param string|null $villageOrBarangay
     */
    public function setVillageOrBarangay(?string $villageOrBarangay): void
    {
        $this->villageOrBarangay = $villageOrBarangay;
    }

    /**
     * @return string|null
     */
    public function getCityOrMunicipality(): ?string
    {
        return $this->cityOrMunicipality;
    }

    /**
     * @param string|null $cityOrMunicipality
     */
    public function setCityOrMunicipality(?string $cityOrMunicipality): void
    {
        $this->cityOrMunicipality = $cityOrMunicipality;
    }

    /**
     * @return string|null
     */
    public function getStateOrProvince(): ?string
    {
        return $this->stateOrProvince;
    }

    /**
     * @param string|null $stateOrProvince
     */
    public function setStateOrProvince(?string $stateOrProvince): void
    {
        $this->stateOrProvince = $stateOrProvince;
    }

    /**
     * @return string|null
     */
    public function getZipOrPostalCode(): ?string
    {
        return $this->zipOrPostalCode;
    }

    /**
     * @param string|null $zipOrPostalCode
     */
    public function setZipOrPostalCode(?string $zipOrPostalCode): void
    {
        $this->zipOrPostalCode = $zipOrPostalCode;
    }

    /**
     * @return string|null
     */
    public function getCountry(): ?string
    {
        return $this->country;
    }

    /**
     * @param string|null $country
     */
    public function setCountry(?string $country): void
    {
        $this->country = $country;
    }

}
