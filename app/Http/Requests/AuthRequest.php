<?php

namespace App\Http\Requests;

use App\Data\AuthData;

class AuthRequest extends BaseRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'identifier' => 'required',
            'password' => 'required'
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'identifier.required' => 'Email is required.',
            'password.required' => 'Password is required.'
        ];
    }

    /**
     * Convert request to data.
     *
     * @return AuthData
     */
    public function toData(): AuthData
    {
        return new AuthData(
            identifier: $this->getInputAsString('identifier'),
            password: $this->getInputAsString('password'),
            remember: $this->getInputAsBoolean('remember', false)
        );
    }

}
