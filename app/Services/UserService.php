<?php

namespace App\Services;

use App\Dtos\ServiceResponseDto;
use App\Dtos\UserDto;
use App\Repositories\UserRepository;
use App\Utils\AppUtil;
use App\Utils\ServiceResponseUtil;
use Illuminate\Support\Str;

class UserService
{

    public function __construct(private readonly UserRepository $userRepository)
    {
    }

    /**
     * @param UserDto $userDto
     *
     * @return ServiceResponseDto
     */
    public function create(UserDto $userDto): ServiceResponseDto
    {
        $user = $this->userRepository->save($userDto);

        if (empty($user)) {
            return ServiceResponseUtil::error('Failed to create user.');
        }

        return ServiceResponseUtil::success('User successfully created.', $user);
    }

    /**
     * @param UserDto $userDto
     *
     * @return ServiceResponseDto
     */
    public function get(UserDto $userDto): ServiceResponseDto
    {
        return ServiceResponseUtil::map(
            $this->userRepository->get($userDto)
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
     * @param UserDto $userDto
     *
     * @return ServiceResponseDto
     */
    public function update(UserDto $userDto): ServiceResponseDto
    {
        $user = $this->userRepository->findById($userDto->getId());

        if (empty($user)) {
            return ServiceResponseUtil::error('User not found.');
        }

        $user = $this->userRepository->save($userDto, $user);

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

}
