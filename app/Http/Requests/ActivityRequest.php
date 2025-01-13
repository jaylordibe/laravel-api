<?php

namespace App\Http\Requests;

use App\Constants\ActivityLogTypeConstant;
use App\Constants\AppPlatformConstant;
use App\Data\ActivityData;
use App\Data\ActivityFilterData;
use Illuminate\Validation\Rule;

class ActivityRequest extends BaseRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(ActivityLogTypeConstant::asList())],
            'description' => ['required', 'string'],
            'properties' => ['required'],
            'properties.platform' => ['required', 'string', Rule::in(AppPlatformConstant::asList())]
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
     * @return ActivityData
     */
    public function toData(): ActivityData
    {
        return new ActivityData(
            userId: $this->getAuthUserData()->id,
            type: $this->getInputAsString('type'),
            description: $this->getInputAsString('description'),
            properties: $this->getInputAsArray('properties', []),
            id: $this->route('activityId'),
            authUser: $this->getAuthUserData(),
            meta: $this->getMetaData()
        );
    }

    /**
     * Transform request to filter data object.
     *
     * @return ActivityFilterData
     */
    public function toFilterData(): ActivityFilterData
    {
        return new ActivityFilterData(
            userId: $this->getInputAsInt('userId', $this->getAuthUserData()->id),
            type: $this->getInputAsString('type'),
            startDate: $this->getInputAsCarbon('startDate'),
            endDate: $this->getInputAsCarbon('endDate'),
            id: $this->getInputAsInt('id'),
            authUser: $this->getAuthUserData(),
            meta: $this->getMetaData()
        );
    }

}