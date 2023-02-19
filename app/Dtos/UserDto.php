<?php

namespace App\Dtos;

use Illuminate\Support\Carbon;

class UserDto extends BaseDto
{

    private ?string $firstName = null;
    private ?string $middleName = null;
    private ?string $lastName = null;
    private ?string $fullName = null;
    private ?string $email = null;
    private ?string $username = null;
    private ?string $role = null;
    private ?string $phoneNumber = null;
    private ?string $address = null;
    private ?Carbon $birthday = null;
    private ?string $profileImage = null;
    private ?string $timezone = null;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @return string|null
     */
    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    /**
     * @param string|null $firstName
     */
    public function setFirstName(?string $firstName): void
    {
        $this->firstName = $firstName;
    }

    /**
     * @return string|null
     */
    public function getMiddleName(): ?string
    {
        return $this->middleName;
    }

    /**
     * @param string|null $middleName
     */
    public function setMiddleName(?string $middleName): void
    {
        $this->middleName = $middleName;
    }

    /**
     * @return string|null
     */
    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    /**
     * @param string|null $lastName
     */
    public function setLastName(?string $lastName): void
    {
        $this->lastName = $lastName;
    }

    /**
     * @return string|null
     */
    public function getFullName(): ?string
    {
        return "{$this->firstName} {$this->lastName}";
    }

    /**
     * @param string|null $fullName
     */
    public function setFullName(?string $fullName): void
    {
        $this->fullName = $fullName;
    }

    /**
     * @return string|null
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * @param string|null $email
     */
    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    /**
     * @return string|null
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * @param string|null $username
     */
    public function setUsername(?string $username): void
    {
        $this->username = $username;
    }

    /**
     * @return string|null
     */
    public function getRole(): ?string
    {
        return $this->role;
    }

    /**
     * @param string|null $role
     */
    public function setRole(?string $role): void
    {
        $this->role = $role;
    }

    /**
     * @return string|null
     */
    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    /**
     * @param string|null $phoneNumber
     */
    public function setPhoneNumber(?string $phoneNumber): void
    {
        $this->phoneNumber = $phoneNumber;
    }

    /**
     * @return string|null
     */
    public function getAddress(): ?string
    {
        return $this->address;
    }

    /**
     * @param string|null $address
     */
    public function setAddress(?string $address): void
    {
        $this->address = $address;
    }

    /**
     * @return Carbon|null
     */
    public function getBirthday(): ?Carbon
    {
        return $this->birthday;
    }

    /**
     * @param Carbon|null $birthday
     */
    public function setBirthday(?Carbon $birthday): void
    {
        $this->birthday = $birthday;
    }

    /**
     * @return string|null
     */
    public function getProfileImage(): ?string
    {
        return $this->profileImage;
    }

    /**
     * @param string|null $profileImage
     */
    public function setProfileImage(?string $profileImage): void
    {
        $this->profileImage = $profileImage;
    }

    /**
     * @return string|null
     */
    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    /**
     * @param string|null $timezone
     */
    public function setTimezone(?string $timezone): void
    {
        $this->timezone = $timezone;
    }
}
