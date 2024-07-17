<?php

namespace App\Http\Requests;

use App\Constants\AppVersionPlatformConstant;
use App\Data\AppVersionData;
use App\Data\AppVersionFilterData;
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
            'version' => 'required|string',
            'description' => 'nullable|string',
            'platform' => ['required', 'string', Rule::in(AppVersionPlatformConstant::asList())],
            'releaseDate' => 'required|date',
            'downloadUrl' => 'nullable|url',
            'forceUpdate' => 'required|boolean'
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
            version: $this->getInputAsString('version'),
            description: $this->getInputAsString('description'),
            platform: $this->getInputAsString('platform'),
            releaseDate: $this->getInputAsCarbon('releaseDate'),
            downloadUrl: $this->getInputAsString('downloadUrl'),
            forceUpdate: $this->getInputAsBoolean('forceUpdate'),
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
            version: $this->getInputAsString('version'),
            platform: $this->getInputAsString('platform'),
            releaseDateStart: $this->getInputAsCarbon('releaseDateStart'),
            releaseDateEnd: $this->getInputAsCarbon('releaseDateEnd'),
            forceUpdate: $this->getInputAsBoolean('forceUpdate'),
            id: $this->getInputAsInt('id'),
            authUser: $this->getAuthUserData(),
            meta: $this->getMetaData()
        );
    }

}
