<?php

namespace App\Services;

use App\Data\CreateUserData;
use App\Data\UserData;
use App\Data\UserFilterData;
use App\Dtos\ServiceResponseDto;
use App\Dtos\UserDto;
use App\Dtos\UserFilterDto;
use App\Repositories\UserRepository;
use App\Utils\AppUtil;
use App\Utils\ServiceResponseUtil;
use Illuminate\Support\Str;

class UserService
{

    public function __construct(
        private readonly UserRepository $userRepository
    )
    {
    }

    /**
     * @param CreateUserData $createUserData
     *
     * @return ServiceResponseDto
     */
    public function create(CreateUserData $createUserData): ServiceResponseDto
    {
        $userData = new UserData(
            firstName: $createUserData->firstName,
            lastName: $createUserData->lastName,
            username: $this->generateUsername($createUserData->email),
            email: $createUserData->email,
            phoneNumber: $createUserData->phoneNumber
        );
        $user = $this->userRepository->create($userData, $createUserData->rawPassword);

        if (empty($user)) {
            return ServiceResponseUtil::error('Failed to create user.');
        }

        return ServiceResponseUtil::success('User successfully created.', $user);
    }

    /**
     * @param UserFilterData $userFilterData
     *
     * @return ServiceResponseDto
     */
    public function get(UserFilterData $userFilterData): ServiceResponseDto
    {
        return ServiceResponseUtil::map(
            $this->userRepository->get($userFilterData)
        );
    }

    /**
     * @param int $id
     *
     * @return ServiceResponseDto
     */
    public function getById(int $id): ServiceResponseDto
    {
        return ServiceResponseUtil::map(
            $this->userRepository->findById($id)
        );
    }

    /**
     * @param UserData $userData
     *
     * @return ServiceResponseDto
     */
    public function update(UserData $userData): ServiceResponseDto
    {
        $user = $this->userRepository->findById($userData->id);

        if (empty($user)) {
            return ServiceResponseUtil::error('User not found.');
        }

        $user = $this->userRepository->save($userData, $user);

        if (empty($user)) {
            return ServiceResponseUtil::error('Failed to update user.');
        }

        return ServiceResponseUtil::success('User successfully updated.', $user);
    }

    /**
     * Delete user.
     *
     * @param int $id
     *
     * @return ServiceResponseDto
     */
    public function delete(int $id): ServiceResponseDto
    {
        $isDeleted = $this->userRepository->delete($id);

        if (!$isDeleted) {
            return ServiceResponseUtil::error('Failed to delete user.');
        }

        return ServiceResponseUtil::success('User successfully deleted.');
    }

    /**
     * @param string $email
     *
     * @return bool
     */
    public function isEmailExists(string $email): bool
    {
        return $this->userRepository->isEmailExists($email);
    }

    /**
     * @param string $username
     *
     * @return bool
     */
    public function isUsernameExists(string $username): bool
    {
        return $this->userRepository->isUsernameExists($username);
    }

    /**
     * @param string $text
     * @param int $count
     *
     * @return string
     */
    public function generateUsername(string $text, int $count = 0): string
    {
        // Remove spaces
        $username = strtolower(str_replace(' ', '', $text));

        if (AppUtil::isValidEmail($username)) {
            $username = Str::before($text, '@');
        }

        // Remove special characters. Only letters and numbers are allowed
        $username = preg_replace('/[^a-zA-Z0-9]/', '', $username);

        if (!empty($count)) {
            $username .= $count;
        }

        $isUsernameExists = $this->isUsernameExists($username);

        if ($isUsernameExists) {
            $username = $this->generateUsername($text, ++$count);
        }

        return $username;
    }

    /**
     * Update username.
     *
     * @param int $id
     * @param string $username
     *
     * @return ServiceResponseDto
     */
    public function updateUsername(int $id, string $username): ServiceResponseDto
    {
        $user = $this->userRepository->updateUsername($id, $username);

        if (empty($user)) {
            return ServiceResponseUtil::error('Failed to update username');
        }

        return ServiceResponseUtil::map($user);
    }

    /**
     * Update email.
     *
     * @param int $id
     * @param string $email
     *
     * @return ServiceResponseDto
     */
    public function updateEmail(int $id, string $email): ServiceResponseDto
    {
        $user = $this->userRepository->updateEmail($id, $email);

        if (empty($user)) {
            return ServiceResponseUtil::error('Failed to update email');
        }

        return ServiceResponseUtil::map($user);
    }

}
