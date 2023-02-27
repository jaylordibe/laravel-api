<?php

namespace App\Http\Requests;

use App\Dtos\UserDto;
use App\Dtos\UserFilterDto;

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
            'birthday' => 'required'
        ];
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
     * Set dto fields.
     *
     * @param UserDto|UserFilterDto $dto
     *
     * @return UserDto|UserFilterDto
     */
    private function setDtoFields(UserDto|UserFilterDto $dto): UserDto|UserFilterDto
    {
        $dto->setMeta($this->getMeta());
        $dto->setAuthUser($this->getAuthUser());
        $dto->setFirstName($this->getInputAsString('firstName'));
        $dto->setMiddleName($this->getInputAsString('middleName'));
        $dto->setLastName($this->getInputAsString('lastName'));
        $dto->setTimezone($this->getInputAsString('timezone'));
        $dto->setPhoneNumber($this->getInputAsString('phoneNumber'));
        $dto->setBirthday($this->getInputAsCarbon('birthday'));

        return $dto;
    }

    /**
     * Convert request to dto.
     *
     * @return UserDto
     */
    public function toDto(): UserDto
    {
        return $this->setDtoFields(new UserDto());
    }

    /**
     * * Convert request to filter dto.
     *
     * @return UserFilterDto
     */
    public function toFilterDto(): UserFilterDto
    {
        $filterDto = $this->setDtoFields(new UserFilterDto());
        $filterDto->setRoles($this->getInputAsArray('roles'));
        $filterDto->setPermissions($this->getInputAsArray('permissions'));

        return $filterDto;
    }

}
