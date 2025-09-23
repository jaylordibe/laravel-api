<?php

namespace App\Http\Requests;

use App\Data\UpdatePasswordData;

class UpdatePasswordRequest extends BaseRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'password' => ['required'],
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
     * @return UpdatePasswordData
     */
    public function toData(): UpdatePasswordData
    {
        return new UpdatePasswordData(
            userId: $this->getAuthUserData()->id,
            password: $this->string('password'),
            passwordConfirmation: $this->string('passwordConfirmation'),
        );
    }

}
