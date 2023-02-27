<?php

namespace App\Http\Requests;

use App\Dtos\GenericDto;

class GenericRequest extends BaseRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return [];
    }

    /**
     * Transform request to data transfer object.
     *
     * @return GenericDto
     */
    public function toDto(): GenericDto
    {
        $genericDto = new GenericDto();
        $genericDto->setMeta($this->getMeta());
        $genericDto->setAuthUser($this->getAuthUser());

        return $genericDto;
    }

}
