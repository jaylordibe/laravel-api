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
            'firstName' => 'required',
            'lastName' => 'required',
            'phoneNumber' => 'required',
            'birthday' => 'required'
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
            firstName: $this->getInputAsString('firstName'),
            lastName: $this->getInputAsString('lastName'),
            username: $this->getInputAsString('username', ''),
            email: $this->getInputAsString('email', ''),
            middleName: $this->getInputAsString('middleName'),
            timezone: $this->getInputAsString('timezone'),
            phoneNumber: $this->getInputAsString('phoneNumber'),
            birthday: $this->getInputAsCarbon('birthday'),
            id: $this->route('userId'),
            meta: $this->getMetaData(),
            authUser: $this->getAuthUserData()
        );
    }

    /**
     * * Convert request to filter data.
     *
     * @return UserFilterData
     */
    public function toFilterData(): UserFilterData
    {
        return new UserFilterData(
            firstName: $this->getInputAsString('firstName'),
            lastName: $this->getInputAsString('lastName'),
            username: $this->getInputAsString('username', ''),
            email: $this->getInputAsString('email', ''),
            middleName: $this->getInputAsString('middleName'),
            timezone: $this->getInputAsString('timezone'),
            phoneNumber: $this->getInputAsString('phoneNumber'),
            birthday: $this->getInputAsCarbon('birthday'),
            id: $this->getInputAsInt('id'),
            meta: $this->getMetaData(),
            authUser: $this->getAuthUserData()
        // Add more filter fields here if needed...
        );
    }

}
