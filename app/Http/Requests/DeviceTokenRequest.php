<?php

namespace App\Http\Requests;

use App\Constants\AppPlatformConstant;
use App\Constants\DeviceOsConstant;
use App\Constants\DeviceTypeConstant;
use App\Data\DeviceTokenData;
use App\Data\DeviceTokenFilterData;
use Illuminate\Validation\Rule;

class DeviceTokenRequest extends BaseRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'appPlatform' => ['required', 'string', Rule::in(AppPlatformConstant::asList())],
            'deviceType' => ['required', 'string', Rule::in(DeviceTypeConstant::asList())],
            'deviceOs' => ['required', 'string', Rule::in(DeviceOsConstant::asList())],
            'deviceOsVersion' => ['required', 'string']
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
     * Transform request to data object.
     *
     * @return DeviceTokenData
     */
    public function toData(): DeviceTokenData
    {
        return new DeviceTokenData(
            userId: $this->getAuthUserData()->id,
            token: $this->getInputAsString('token'),
            appPlatform: $this->getInputAsString('appPlatform'),
            deviceType: $this->getInputAsString('deviceType'),
            deviceOs: $this->getInputAsString('deviceOs'),
            deviceOsVersion: $this->getInputAsString('deviceOsVersion'),
            id: $this->route('deviceTokenId'),
            authUser: $this->getAuthUserData(),
            meta: $this->getMetaData()
        );
    }

    /**
     * Transform request to filter data object.
     *
     * @return DeviceTokenFilterData
     */
    public function toFilterData(): DeviceTokenFilterData
    {
        return new DeviceTokenFilterData(
            userId: $this->getAuthUserData()->id,
            appPlatform: $this->getInputAsString('appPlatform'),
            deviceType: $this->getInputAsString('deviceType'),
            deviceOs: $this->getInputAsString('deviceOs'),
            deviceOsVersion: $this->getInputAsString('deviceOsVersion'),
            id: $this->getInputAsInt('id'),
            authUser: $this->getAuthUserData(),
            meta: $this->getMetaData()
        );
    }

}
