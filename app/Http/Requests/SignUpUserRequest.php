<?php

namespace App\Http\Requests;

use App\Data\SignUpUserData;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SignUpUserRequest extends BaseRequest
{

    /**
     * Prepare the data for validation. Emails are stored lower-cased (User::email), so compare them that way.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

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
            'email' => ['required', 'email', Rule::unique(User::class, 'email')],
            'phoneNumber' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8'],
            'passwordConfirmation' => ['required', 'same:password', 'min:8']
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
            firstName: $this->string('firstName'),
            lastName: $this->string('lastName'),
            email: $this->string('email'),
            phoneNumber: $this->string('phoneNumber'),
            password: $this->string('password')
        );
    }

}
