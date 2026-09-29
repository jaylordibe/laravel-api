<?php

namespace App\Http\Requests;

use App\Data\ResetPasswordData;

class ResetPasswordRequest extends BaseRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8'],
            'passwordConfirmation' => ['required', 'same:password']
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
     * @return ResetPasswordData
     */
    public function toData(): ResetPasswordData
    {
        return new ResetPasswordData(
            email: $this->string('email'),
            token: $this->string('token'),
            password: $this->string('password')
        );
    }

}
