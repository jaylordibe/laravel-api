<?php

namespace App\Http\Requests;

use App\Dtos\AuthDto;

class AuthRequest extends BaseRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
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
    public function messages()
    {
        return [
            'identifier.required' => 'Email is required.',
            'password.required' => 'Password is required.'
        ];
    }

    /**
     * Convert request to dto.
     *
     * @return AuthDto
     */
    public function toDto(): AuthDto
    {
        $authDto = new AuthDto();
        $authDto->setIdentifier($this->getInputAsString('identifier'));
        $authDto->setPassword($this->getInputAsString('password'));
        $authDto->setRemember($this->getInputAsBoolean('remember'));

        return $authDto;
    }
}
