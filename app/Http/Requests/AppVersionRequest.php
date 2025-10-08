<?php

namespace App\Http\Requests;

use App\Data\AppVersionData;
use App\Data\AppVersionFilterData;
use App\Enums\AppPlatform;
use App\Rules\UtcIsoStringRule;
use Illuminate\Validation\Rule;

class AppVersionRequest extends BaseRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'version' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'platform' => ['required', 'string', Rule::enum(AppPlatform::class)],
            'releaseDate' => ['required', 'date', new UtcIsoStringRule()],
            'downloadUrl' => ['nullable', 'url'],
            'forceUpdate' => ['required', 'boolean']
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
     * @return AppVersionData
     */
    public function toData(): AppVersionData
    {
        return new AppVersionData(
            version: $this->string('version'),
            description: $this->string('description'),
            platform: $this->enum('platform', AppPlatform::class),
            releaseDate: $this->date('releaseDate'),
            downloadUrl: $this->string('downloadUrl'),
            forceUpdate: $this->boolean('forceUpdate'),
            id: $this->route('appVersionId'),
            authUser: $this->getAuthUserData(),
            meta: $this->getMetaData()
        );
    }

    /**
     * Transform request to filter data object.
     *
     * @return AppVersionFilterData
     */
    public function toFilterData(): AppVersionFilterData
    {
        return new AppVersionFilterData(
            version: $this->string('version'),
            platform: $this->enum('platform', AppPlatform::class),
            releaseDateStart: $this->date('releaseDateStart'),
            releaseDateEnd: $this->date('releaseDateEnd'),
            forceUpdate: $this->boolean('forceUpdate', null),
            id: $this->integer('id', null),
            authUser: $this->getAuthUserData(),
            meta: $this->getMetaData()
        );
    }

}
