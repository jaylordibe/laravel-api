<?php

namespace App\Http\Requests;

use App\Data\ActivityData;
use App\Data\ActivityFilterData;
use App\Enums\ActivityLogType;
use App\Enums\AppPlatform;
use Illuminate\Validation\Rule;

class ActivityLogRequest extends BaseRequest
{

    /**
     * {@inheritDoc}
     */
    protected const array ALLOWED_RELATIONS = ['causer'];

    /**
     * {@inheritDoc}
     */
    protected const array SORTABLE_FIELDS = ['id', 'created_at', 'updated_at', 'log_name'];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'logName' => ['required', 'string', Rule::enum(ActivityLogType::class)],
            'description' => ['required', 'string'],
            'properties' => ['required'],
            'properties.platform' => ['required', 'string', Rule::enum(AppPlatform::class)]
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
            logName: $this->string('logName'),
            description: $this->string('description'),
            properties: $this->array('properties'),
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
        // The caller's own logs unless a specific other user is asked for. 0, a non-number or an empty
        // value is never "no filter" — it falls back to the caller. Reading another user's logs is
        // authorized in ActivityLogService::getPaginated.
        $requestedUserId = $this->integer('userId');

        return new ActivityFilterData(
            userId: $requestedUserId > 0 ? $requestedUserId : $this->getAuthUserData()->id,
            type: $this->string('type'),
            startDate: $this->date('startDate'),
            endDate: $this->date('endDate'),
            id: $this->has('id') ? $this->integer('id') : null,
            authUser: $this->getAuthUserData(),
            meta: $this->getMetaData()
        );
    }

}
