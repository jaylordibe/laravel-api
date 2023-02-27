<?php

namespace App\Http\Requests;

use App\Constants\RoleConstant;
use App\Dtos\UserDto;

class UserRequest extends BaseRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'firstName' => 'required',
            'lastName' => 'required',
            'phoneNumber' => 'required',
            'branchId' => 'required'
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
            'firstName.required' => 'First name is required.',
            'lastName.required' => 'Last name is required.',
            'phoneNumber.required' => 'Phone number is required.',
            'branchId.required' => 'Branch id is required.'
        ];
    }

    /**
     * Convert request to dto.
     *
     * @return UserDto
     */
    public function toDto(): UserDto
    {
        $userDto = new UserDto();
        $userDto->setMeta($this->getMeta());
        $userDto->setAuthUser($this->getAuthUser());
        $userDto->setCreatedBy($this->getAuthUser()->getId());
        $userDto->setUpdatedBy($this->getAuthUser()->getId());
        $userDto->setFirstName($this->getInputAsString('firstName'));
        $userDto->setMiddleName($this->getInputAsString('middleName'));
        $userDto->setLastName($this->getInputAsString('lastName'));
        $userDto->setEmail($this->getInputAsString('email'));
        $userDto->setUsername($this->getInputAsString('username'));
        $userDto->setRole($this->getInputAsString('role'));
        $userDto->setPhoneNumber($this->getInputAsString('phoneNumber'));
        $userDto->setAddress($this->getInputAsString('address'));
        $userDto->setBirthday($this->getInputAsCarbon('birthday'));
        $userDto->setTimezone($this->getInputAsString('timezone'));

        return $userDto;
    }
}
