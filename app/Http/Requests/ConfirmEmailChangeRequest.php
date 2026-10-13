<?php

namespace App\Http\Requests;

class ConfirmEmailChangeRequest extends BaseRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * Both values come from the link the client app was opened with. The token is the 64-character value
     * UserService::requestEmailChange issued; anything else cannot match and is refused here.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'userId' => ['required', 'integer', 'min:1'],
            'token' => ['required', 'string', 'size:64']
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

}
