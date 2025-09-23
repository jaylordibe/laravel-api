<?php

namespace App\Http\Requests;

use App\Data\UserData;
use App\Data\UserFilterData;

class UserRequest extends BaseRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'firstName' => ['required', 'string'],
            'lastName' => ['required', 'string'],
            'phoneNumber' => ['required', 'string'],
            'birthday' => ['required', 'string']
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
     * @return UserData
     */
    public function toData(): UserData
    {
        return new UserData(
            firstName: $this->string('firstName'),
            lastName: $this->string('lastName'),
            username: $this->string('username', ''),
            email: $this->string('email', ''),
            middleName: $this->string('middleName'),
            timezone: $this->string('timezone'),
            phoneNumber: $this->string('phoneNumber'),
            birthday: $this->date('birthday'),
            id: $this->route('userId'),
            meta: $this->getMetaData(),
            authUser: $this->getAuthUserData()
        );
    }

    /**
     * Convert request to filter data.
     *
     * @return UserFilterData
     */
    public function toFilterData(): UserFilterData
    {
        return new UserFilterData(
            // Add UserFilterData properties here
            id: $this->integer('id'),
            meta: $this->getMetaData(),
            authUser: $this->getAuthUserData()
        );
    }

}
