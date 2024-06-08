<?php

namespace App\Http\Requests;

use App\Data\SignUpUserData;

class SignUpUserRequest extends BaseRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'firstName' => 'required|string',
            'lastName' => 'required|string',
            'email' => 'required|email',
            'phoneNumber' => 'required|string',
            'password' => 'required|string|min:8',
            'passwordConfirmation' => 'required|same:password|min:8'
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
     * @return SignUpUserData
     */
    public function toData(): SignUpUserData
    {
        return new SignUpUserData(
            firstName: $this->getInputAsString('firstName'),
            lastName: $this->getInputAsString('lastName'),
            email: $this->getInputAsString('email'),
            phoneNumber: $this->getInputAsString('phoneNumber'),
            rawPassword: $this->getInputAsString('password'),
            rawPasswordConfirmation: $this->getInputAsString('passwordConfirmation')
        );
    }

}
