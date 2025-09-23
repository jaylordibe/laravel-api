<?php

namespace App\Http\Requests;

use App\Data\AddressData;
use App\Data\AddressFilterData;
use Illuminate\Support\Facades\Auth;

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
            'address' => ['required', 'string'],
            'villageOrBarangay' => ['required', 'string'],
            'cityOrMunicipality' => ['required', 'string'],
            'stateOrProvince' => ['required', 'string'],
            'zipOrPostalCode' => ['required', 'string'],
            'country' => ['required', 'string']
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
            userId: Auth::user()->id,
            address: $this->string('address'),
            villageOrBarangay: $this->string('villageOrBarangay'),
            cityOrMunicipality: $this->string('cityOrMunicipality'),
            stateOrProvince: $this->string('stateOrProvince'),
            zipOrPostalCode: $this->string('zipOrPostalCode'),
            country: $this->string('country'),
            id: $this->route('addressId'),
            authUser: $this->getAuthUserData(),
            meta: $this->getMetaData()
        );
    }

    /**
     * Convert request to filter data.
     *
     * @return AddressFilterData
     */
    public function toFilterData(): AddressFilterData
    {
        return new AddressFilterData(
            userId: Auth::user()->id,
            villageOrBarangay: $this->string('villageOrBarangay'),
            cityOrMunicipality: $this->string('cityOrMunicipality'),
            stateOrProvince: $this->string('stateOrProvince'),
            zipOrPostalCode: $this->string('zipOrPostalCode'),
            country: $this->string('country'),
            id: $this->integer('id'),
            authUser: $this->getAuthUserData(),
            meta: $this->getMetaData()
        );
    }

}
