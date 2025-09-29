<?php

namespace App\Http\Requests;

use App\Data\CreateUserData;
use App\Enums\UserRole;
use Illuminate\Validation\Rule;

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
            'firstName' => ['required', 'string'],
            'lastName' => ['required', 'string'],
            'email' => ['required', 'email'],
            'phoneNumber' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8'],
            'passwordConfirmation' => ['required', 'same:password', 'min:8'],
            'role' => ['required', 'string', Rule::enum(UserRole::class)]
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
            firstName: $this->string('firstName'),
            lastName: $this->string('lastName'),
            email: $this->string('email'),
            phoneNumber: $this->string('phoneNumber'),
            password: $this->string('password'),
            role: $this->enum('role', UserRole::class)
        );
    }

}
