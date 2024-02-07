<?php

namespace App\Http\Requests;

use App\Data\CreateUserData;

class CreateUserRequest extends BaseRequest
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
            'email' => 'required',
            'phoneNumber' => 'required',
            'password' => 'required',
            'passwordConfirmation' => 'required|same:password'
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
     * @return CreateUserData
     */
    public function toData(): CreateUserData
    {
        return new CreateUserData(
            firstName: $this->getInputAsString('firstName'),
            lastName: $this->getInputAsString('lastName'),
            email: $this->getInputAsString('email'),
            phoneNumber: $this->getInputAsString('phoneNumber'),
            rawPassword: $this->getInputAsString('password'),
            rawPasswordConfirmation: $this->getInputAsString('passwordConfirmation')
        );
    }

}
