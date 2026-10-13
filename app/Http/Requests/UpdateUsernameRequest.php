<?php

namespace App\Http\Requests;

use App\Constants\UserConstant;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateUsernameRequest extends BaseRequest
{

    /**
     * Prepare the data for validation. Usernames are stored lower-cased (User::username), so compare them
     * that way.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('username'))) {
            $this->merge(['username' => Str::lower(trim($this->input('username')))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Letters, digits, dot, underscore and hyphen. No `@`, so a username can never look like an email —
     * sign-in would route it to the email column and it could never be signed in with.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'username' => [
                'required',
                'string',
                'min:3',
                'max:' . UserConstant::MAX_USERNAME_LENGTH,
                'regex:/^[a-z0-9._-]+$/',
                Rule::unique(User::class, 'username')->ignore($this->getAuthUserData()?->id)
            ]
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
            'username.regex' => 'The username may only contain letters, numbers, dots, underscores and hyphens.'
        ];
    }

}
