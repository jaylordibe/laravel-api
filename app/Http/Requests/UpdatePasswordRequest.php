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
            'password' => ['required', 'string', 'min:8'],
            'passwordConfirmation' => ['required', 'same:password', 'min:8'],
            // Required when changing your own password; a missing or wrong value is rejected with 400.
            'currentPassword' => ['nullable', 'string']
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
            currentPassword: $this->string('currentPassword'),
        );
    }

}
