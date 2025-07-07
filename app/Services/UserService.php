<?php

namespace App\Services;

use App\Constants\RoleConstant;
use App\Data\CreateUserData;
use App\Data\SignUpUserData;
use App\Data\UpdatePasswordData;
use App\Data\UserData;
use App\Data\UserFilterData;
use App\Exceptions\BadRequestException;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Utils\AppUtil;
use App\Utils\FileUtil;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class UserService
{

    public function __construct(
        private readonly UserRepository $userRepository
    )
    {
    }

    /**
     * @param SignUpUserData $signUpUserData
     *
     * @return User|null
     * @throws BadRequestException
     */
    public function signUp(SignUpUserData $signUpUserData): ?User
    {
        $userData = new UserData(
            firstName: $signUpUserData->firstName,
            lastName: $signUpUserData->lastName,
            username: $this->generateUsername($signUpUserData->email),
            email: $signUpUserData->email,
            phoneNumber: $signUpUserData->phoneNumber
        );
        $user = $this->userRepository->create($userData, $signUpUserData->password);

        if (empty($user)) {
            throw new BadRequestException('Sign up failed.');
        }

        $user->sendEmailVerificationNotification();

        return $user;
    }

    /**
     * Verify user email.
     *
     * @param int $userId
     *
     * @return User|null
     * @throws BadRequestException
     */
    public function verifyEmail(int $userId): ?User
    {
        $user = $this->userRepository->findById($userId);

        if (empty($user)) {
            throw new BadRequestException('User not found.');
        }

        if ($user->hasVerifiedEmail()) {
            throw new BadRequestException('Email already verified.');
        }

        $user->markEmailAsVerified();

        return $user;
    }

    /**
     * @param CreateUserData $createUserData
     *
     * @return User|null
     * @throws BadRequestException
     */
    public function create(CreateUserData $createUserData): ?User
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
            throw new BadRequestException('Failed to create user.');
        }

        return $user;
    }

    /**
     * @param UserFilterData $userFilterData
     *
     * @return LengthAwarePaginator<User>
     */
    public function getPaginated(UserFilterData $userFilterData): LengthAwarePaginator
    {
        return $this->userRepository->getPaginated($userFilterData);
    }

    /**
     * @param int $id
     * @param array $relations
     *
     * @return User|null
     * @throws BadRequestException
     */
    public function getById(int $id, array $relations = []): ?User
    {
        $user = $this->userRepository->findById($id, $relations);

        if (empty($user)) {
            throw new BadRequestException('User not found.');
        }

        return $user;
    }

    /**
     * @param UserData $userData
     *
     * @return User|null
     * @throws BadRequestException
     */
    public function update(UserData $userData): ?User
    {
        $user = $this->userRepository->findById($userData->id);

        if (empty($user)) {
            throw new BadRequestException('User not found.');
        }

        $user = $this->userRepository->save($userData, $user);

        if (empty($user)) {
            throw new BadRequestException('Failed to update user.');
        }

        return $user;
    }

    /**
     * Delete user.
     *
     * @param int $id
     *
     * @return bool
     * @throws BadRequestException
     */
    public function delete(int $id): bool
    {
        $isDeleted = $this->userRepository->delete($id);

        if (!$isDeleted) {
            throw new BadRequestException('Failed to delete user.');
        }

        return true;
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
     * @return User|null
     * @throws BadRequestException
     */
    public function updateUsername(int $id, string $username): ?User
    {
        $user = $this->userRepository->updateUsername($id, $username);

        if (empty($user)) {
            throw new BadRequestException('Failed to update username');
        }

        return $user;
    }

    /**
     * Update email.
     *
     * @param int $id
     * @param string $email
     *
     * @return User|null
     * @throws BadRequestException
     */
    public function updateEmail(int $id, string $email): ?User
    {
        $user = $this->userRepository->updateEmail($id, $email);

        if (empty($user)) {
            throw new BadRequestException('Failed to update email');
        }

        return $user;
    }

    /**
     * Update user password.
     *
     * @param UpdatePasswordData $changePasswordData
     *
     * @return User|null
     * @throws BadRequestException
     */
    public function updatePassword(UpdatePasswordData $changePasswordData): ?User
    {
        /** @var User $authUser */
        $authUser = Auth::user();
        $isAdmin = $authUser->hasRole([RoleConstant::SYSTEM_ADMIN, RoleConstant::APP_ADMIN]);

        if (!$isAdmin && $authUser->id !== $changePasswordData->userId) {
            throw new BadRequestException('Unauthorized to update password.');
        }

        $user = $this->userRepository->updatePassword($changePasswordData);

        if (empty($user)) {
            throw new BadRequestException('Failed to update password.');
        }

        return $user;
    }

    /**
     * Update profile photo.
     *
     * @param int $id
     * @param UploadedFile $profilePhoto
     *
     * @return User|null
     * @throws BadRequestException
     */
    public function updateProfilePhoto(int $id, UploadedFile $profilePhoto): ?User
    {
        $path = FileUtil::upload($profilePhoto);
        $profilePhotoUrl = FileUtil::getUrl($path);
        $user = $this->userRepository->updateProfilePhotoUrl($id, $profilePhotoUrl);

        if (empty($user)) {
            throw new BadRequestException('Failed to update email');
        }

        return $user;
    }

}
