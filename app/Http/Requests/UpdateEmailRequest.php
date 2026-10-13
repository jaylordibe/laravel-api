<?php

namespace App\Http\Requests;

use App\Data\UpdateEmailData;
use Illuminate\Support\Str;

class UpdateEmailRequest extends BaseRequest
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
     * Uniqueness is checked in UserService::requestEmailChange AFTER the current password, so a bearer token
     * alone cannot probe which addresses have accounts.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'email' => ['required', ...self::EMAIL_RULES],
            'currentPassword' => ['required', 'string']
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
     * @return UpdateEmailData
     */
    public function toData(): UpdateEmailData
    {
        return new UpdateEmailData(
            userId: $this->getAuthUserData()->id,
            email: $this->string('email'),
            currentPassword: $this->string('currentPassword')
        );
    }

}
