<?php

namespace App\Http\Requests;

use App\Data\AddressData;
use App\Data\AddressFilterData;

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
            id: $this->route('addressId'),
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
            id: $this->getInputAsInt('id'),
            meta: $this->getMetaData(),
            authUser: $this->getAuthUserData()
        // Add more filter fields here if needed...
        );
    }

}
