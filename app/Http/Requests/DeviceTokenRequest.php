<?php

namespace App\Http\Requests;

use App\Data\DeviceTokenData;
use App\Data\DeviceTokenFilterData;
use App\Enums\AppPlatform;
use App\Enums\DeviceOs;
use App\Enums\DeviceType;
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
            'appPlatform' => ['required', 'string', Rule::enum(AppPlatform::class)],
            'deviceType' => ['required', 'string', Rule::enum(DeviceType::class)],
            'deviceOs' => ['required', 'string', Rule::enum(DeviceOs::class)],
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
            token: $this->string('token'),
            appPlatform: $this->enum('appPlatform', AppPlatform::class),
            deviceType: $this->enum('deviceType', DeviceType::class),
            deviceOs: $this->enum('deviceOs', DeviceOs::class),
            deviceOsVersion: $this->string('deviceOsVersion'),
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
            appPlatform: $this->enum('appPlatform', AppPlatform::class),
            deviceType: $this->enum('deviceType', DeviceType::class),
            deviceOs: $this->enum('deviceOs', DeviceOs::class),
            deviceOsVersion: $this->string('deviceOsVersion'),
            id: $this->has('id') ? $this->integer('id') : null,
            authUser: $this->getAuthUserData(),
            meta: $this->getMetaData()
        );
    }

}
