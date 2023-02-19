<?php

namespace App\Services;

use App\Constants\AppConstant;
use App\Constants\UserRoleConstant;
use App\Dtos\UserDto;
use App\Models\Custom\ServiceResponse;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Utils\AppUtil;
use Illuminate\Support\Str;

class UserService
{

    private UserRepository $userRepository;

    /**
     * @param UserRepository $userRepository
     */
    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * @param int $id
     * @param array|null $relations
     * @return UserDto
     */
    public function findById(int $id, array $relations = null): UserDto
    {
        return $this->userRepository->findById($id, $relations)->toDto();
    }

    /**
     * @param UserDto $userDto
     * @return ServiceResponse
     */
    public function create(UserDto $userDto): ServiceResponse
    {
        $user = $this->userRepository->save($userDto);

        if (empty($user)) {
            return ServiceResponse::error('Failed to create user.');
        }

        return ServiceResponse::success('User successfully created.', $user);
    }

    /**
     * @param UserDto $userDto
     * @return ServiceResponse
     */
    public function get(UserDto $userDto): ServiceResponse
    {
        return ServiceResponse::map(
            $this->userRepository->get($userDto)
        );
    }

    /**
     * @param int $id
     * @return ServiceResponse
     */
    public function getById(int $id): ServiceResponse
    {
        return ServiceResponse::map(
            $this->userRepository->findById($id)
        );
    }

    /**
     * @param UserDto $userDto
     * @return ServiceResponse
     */
    public function update(UserDto $userDto): ServiceResponse
    {
        $user = $this->userRepository->findById($userDto->getId());

        if (empty($user)) {
            return ServiceResponse::error('User not found.');
        }

        $user = $this->userRepository->save($userDto, $user);

        if (empty($user)) {
            return ServiceResponse::error('Failed to update user.');
        }

        return ServiceResponse::success('User successfully updated.', $user);
    }

    /**
     * Delete user.
     * @param int $id
     * @return ServiceResponse
     */
    public function delete(int $id): ServiceResponse
    {
        $isDeleted = $this->userRepository->delete($id);

        if (!$isDeleted) {
            return ServiceResponse::error('Failed to delete user.');
        }

        return ServiceResponse::success('User successfully deleted.');
    }

    /**
     * @param string $email
     * @return bool
     */
    public function isEmailExists(string $email): bool
    {
        return $this->userRepository->isEmailExists($email);
    }

    /**
     * @param string $username
     * @return bool
     */
    public function isUsernameExists(string $username): bool
    {
        return $this->userRepository->isUsernameExists($username);
    }

    /**
     * @param string $text
     * @param int $count
     * @return string
     */
    public function generateEmail(string $text, int $count = 0): string
    {
        $email = strtolower(str_replace(' ', '', $text));

        if (!empty($count)) {
            $email .= $count;
        }

        $email .= AppConstant::WEB_MAIL;
        $isEmailExists = $this->isEmailExists($email);

        if ($isEmailExists) {
            $email = $this->generateEmail($text, ++$count);
        }

        return $email;
    }

    /**
     * @param string $text
     * @param int $count
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
