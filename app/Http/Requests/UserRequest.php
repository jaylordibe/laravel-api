<?php

namespace App\Http\Requests;

use App\Data\UserData;
use App\Data\UserFilterData;
use App\Enums\Gender;

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
            'birthdate' => ['required', 'string']
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
            middleName: $this->string('middleName'),
            lastName: $this->string('lastName'),
            username: $this->string('username', ''),
            email: $this->string('email', ''),
            emailVerifiedAt: $this->date('emailVerifiedAt'),
            phoneNumber: $this->string('phoneNumber'),
            gender: $this->enum('gender', Gender::class),
            birthdate: $this->date('birthdate'),
            timezone: $this->string('timezone'),
            profileImage: $this->string('profileImage'),
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
            id: $this->integer('id'),
            meta: $this->getMetaData(),
            authUser: $this->getAuthUserData()
        );
    }

}
