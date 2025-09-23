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
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string']
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
            'identifier.required' => 'Username or email is required.',
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
            identifier: $this->string('identifier'),
            password: $this->string('password'),
            remember: $this->boolean('remember')
        );
    }

}
