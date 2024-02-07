<?php

namespace App\Http\Requests;

use App\Data\AddressData;
use App\Data\AddressFilterData;
use App\Dtos\AddressDto;
use App\Dtos\AddressFilterDto;

class AddressRequest extends BaseRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'address' => 'required',
            'villageOrBarangay' => 'required',
            'cityOrMunicipality' => 'required',
            'stateOrProvince' => 'required',
            'zipOrPostalCode' => 'required',
            'country' => 'required'
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Set dto fields.
     *
     * @param AddressDto|AddressFilterDto $dto
     *
     * @return AddressDto|AddressFilterDto
     */
    private function setDtoFields(AddressDto|AddressFilterDto $dto): AddressDto|AddressFilterDto
    {
        $dto->setMeta($this->getMeta());
        $dto->setAuthUser($this->getAuthUser());
        $dto->setAddress($this->getInputAsString('address'));
        $dto->setVillageOrBarangay($this->getInputAsString('villageOrBarangay'));
        $dto->setCityOrMunicipality($this->getInputAsString('cityOrMunicipality'));
        $dto->setStateOrProvince($this->getInputAsString('stateOrProvince'));
        $dto->setZipOrPostalCode($this->getInputAsString('zipOrPostalCode'));
        $dto->setCountry($this->getInputAsString('country'));

        return $dto;
    }

    /**
     * Convert request to dto.
     *
     * @return AddressDto
     */
    public function toDto(): AddressDto
    {
        return $this->setDtoFields(new AddressDto());
    }

    /**
     * * Convert request to filter dto.
     *
     * @return AddressFilterDto
     */
    public function toFilterDto(): AddressFilterDto
    {
        $filterDto = $this->setDtoFields(new AddressFilterDto());

        return $filterDto;
    }

    /**
     * Convert request to data.
     *
     * @return AddressData
     */
    public function toData(): AddressData
    {
        return new AddressData(
            userId: $this->getAuthUser()->id,
            address: $this->getInputAsString('address'),
            villageOrBarangay: $this->getInputAsString('villageOrBarangay'),
            cityOrMunicipality: $this->getInputAsString('cityOrMunicipality'),
            stateOrProvince: $this->getInputAsString('stateOrProvince'),
            zipOrPostalCode: $this->getInputAsString('zipOrPostalCode'),
            country: $this->getInputAsString('country'),
            meta: $this->getMetaData(),
            authUser: $this->getAuthUserData()
        );
    }

    /**
     * * Convert request to filter data.
     *
     * @return AddressFilterData
     */
    public function toFilterData(): AddressFilterData
    {
        return new AddressFilterData(
            userId: $this->getAuthUser()->id,
            address: $this->getInputAsString('address'),
            villageOrBarangay: $this->getInputAsString('villageOrBarangay'),
            cityOrMunicipality: $this->getInputAsString('cityOrMunicipality'),
            stateOrProvince: $this->getInputAsString('stateOrProvince'),
            zipOrPostalCode: $this->getInputAsString('zipOrPostalCode'),
            country: $this->getInputAsString('country'),
            meta: $this->getMetaData(),
            authUser: $this->getAuthUserData()
        // Add more filter fields here if needed...
        );
    }

}
