<?php

namespace App\Http\Requests;

use App\Dtos\AddressDto;
use App\Dtos\AddressFilterDto;

class AddressRequest extends BaseRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
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
    public function messages()
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

}
