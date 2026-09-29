<?php

namespace App\Services;

use App\Data\CreateUserData;
use App\Data\ResetPasswordData;
use App\Data\SignUpUserData;
use App\Data\UpdatePasswordData;
use App\Data\UserData;
use App\Data\UserFilterData;
use App\Enums\UserRole;
use App\Exceptions\BadRequestException;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Repositories\UserRoleRepository;
use App\Utils\AppUtil;
use App\Utils\FileUtil;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Laravel\Passport\RefreshToken;
use Throwable;

class UserService
{

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserRoleRepository $userRoleRepository
    )
    {
    }

    /**
     * Revoke all user tokens, optionally keeping one (the session making the request).
     * Used when a password changes and when the user signs out of every session.
     *
     * @param User $user
     * @param string|null $exceptTokenId
     *
     * @return void
     * @throws Throwable
     */
    public function revokeAllUserTokens(User $user, ?string $exceptTokenId = null): void
    {
        DB::transaction(function () use ($user, $exceptTokenId) {
            $tokens = $user->tokens()->when($exceptTokenId, fn ($query) => $query->whereKeyNot($exceptTokenId));
            $tokenIds = (clone $tokens)->pluck('id');

            if ($tokenIds->isNotEmpty()) {
                RefreshToken::whereIn('access_token_id', $tokenIds)
                    ->update(['revoked' => true]);
            }

            $tokens->update(['revoked' => true]);
        });
    }

    /**
     * Sign up a new user and send the email verification link.
     *
     * @param SignUpUserData $signUpUserData
     *
     * @return User|null
     * @throws BadRequestException
     */
    public function signUp(SignUpUserData $signUpUserData): ?User
    {
        $userData = new UserData(
            firstName: $signUpUserData->firstName,
            middleName: null,
            lastName: $signUpUserData->lastName,
            username: $this->generateUsername($signUpUserData->email),
            email: $signUpUserData->email,
            emailVerifiedAt: null,
            phoneNumber: $signUpUserData->phoneNumber,
            gender: null,
            birthdate: null,
            timezone: null,
            profileImage: null,
            address: null
        );
        $user = $this->userRepository->create($userData, $signUpUserData->password);

        if (empty($user)) {
            throw new BadRequestException('Sign up failed.');
        }

        $user->sendEmailVerificationNotification();

        return $user;
    }

    /**
     * Send a password reset link if the email belongs to an account.
     *
     * @param string $email
     *
     * @return void
     */
    public function sendPasswordResetLink(string $email): void
    {
        Password::sendResetLink(['email' => Str::lower(trim($email))]);
    }

    /**
     * Reset a password with a token from the reset link, then sign out every session.
     *
     * @param ResetPasswordData $resetPasswordData
     *
     * @return void
     * @throws BadRequestException
     */
    public function resetPassword(ResetPasswordData $resetPasswordData): void
    {
        $status = Password::reset(
            [
                'email' => Str::lower(trim($resetPasswordData->email)),
                'token' => $resetPasswordData->token,
                'password' => $resetPasswordData->password
            ],
            function (User $user, string $password): void {
                $this->userRepository->updatePassword(new UpdatePasswordData(
                    userId: $user->id,
                    password: $password,
                    passwordConfirmation: $password,
                    currentPassword: ''
                ));
                $this->revokeAllUserTokens($user);
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw new BadRequestException(__($status));
        }
    }

    /**
     * Send a new verification link if the email belongs to an account that is not verified yet.
     *
     * @param string $email
     *
     * @return void
     */
    public function resendEmailVerification(string $email): void
    {
        $user = $this->userRepository->findByEmail($email);

        if (!empty($user) && !$user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }
    }

    /**
     * Verify user email.
     *
     * The hash binds the link to the current email. Verifying twice succeeds.
     *
     * @param int $userId
     * @param string $hash
     *
     * @return User|null
     * @throws BadRequestException
     */
    public function verifyEmail(int $userId, string $hash): ?User
    {
        $user = $this->userRepository->findById($userId);

        if (empty($user) || !hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            throw new BadRequestException('Invalid verification link.');
        }

        if ($user->hasVerifiedEmail()) {
            return $user;
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
        try {
            return DB::transaction(function () use ($createUserData) {
                $userData = new UserData(
                    firstName: $createUserData->firstName,
                    middleName: null,
                    lastName: $createUserData->lastName,
                    username: $this->generateUsername($createUserData->email),
                    email: $createUserData->email,
                    emailVerifiedAt: null,
                    phoneNumber: $createUserData->phoneNumber,
                    gender: null,
                    birthdate: null,
                    timezone: null,
                    profileImage: null,
                    address: null
                );
                $user = $this->userRepository->create($userData, $createUserData->password);

                if (empty($user)) {
                    throw new BadRequestException('Failed to create user.');
                }

                $userRole = $this->userRoleRepository->findByName($createUserData->role->value);

                if (empty($userRole)) {
                    throw new BadRequestException('User role not found.');
                }

                $user->assignRole($userRole);
                // Sign-in requires a verified email, so an invited user needs the link too.
                $user->sendEmailVerificationNotification();

                return $user;
            });
        } catch (BadRequestException $e) {
            // Preserve the specific, user-facing message from inside the transaction.
            throw $e;
        } catch (Throwable $e) {
            // Log the real cause server-side; never surface a raw internal (e.g. DB) message to the client.
            Log::error('Failed to create user.', ['exception' => $e]);

            throw new BadRequestException('Failed to create user.');
        }
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
    public function generateEmail(string $text, int $count = 0): string
    {
        $email = strtolower(str_replace(' ', '', $text));

        if (!empty($count)) {
            $email .= $count;
        }

        $email .= config('custom.app_domain');
        $isEmailExists = $this->isEmailExists($email);

        if ($isEmailExists) {
            $email = $this->generateEmail($text, ++$count);
        }

        return $email;
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

        // A new address must be verified, like Fortify's profile update.
        if ($user->wasChanged('email')) {
            $user->sendEmailVerificationNotification();
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
     * @throws Throwable
     */
    public function updatePassword(UpdatePasswordData $changePasswordData): ?User
    {
        /** @var User $authUser */
        $authUser = Auth::user();
        $isAdmin = $authUser->hasRole([UserRole::SYSTEM_ADMIN->value, UserRole::APP_ADMIN->value]);

        if (!$isAdmin && $authUser->id !== $changePasswordData->userId) {
            throw new BadRequestException('Unauthorized to update password.');
        }

        // Changing your own password (on either route) needs the current one; an admin reset does not.
        $isSelfChange = $authUser->id === $changePasswordData->userId;

        if ($isSelfChange && !Hash::check($changePasswordData->currentPassword, $authUser->password)) {
            throw new BadRequestException('Current password is incorrect.');
        }

        $user = $this->userRepository->updatePassword($changePasswordData);

        if (empty($user)) {
            throw new BadRequestException('Failed to update password.');
        }

        // Like logoutOtherDevices(): keep the caller's own session; an admin reset signs out everywhere.
        $this->revokeAllUserTokens($user, $isSelfChange ? $authUser->token()?->id : null);

        return $user;
    }

    /**
     * Update profile image.
     *
     * @param int $id
     * @param UploadedFile $profileImageFile
     *
     * @return User|null
     * @throws BadRequestException
     */
    public function updateProfileImage(int $id, UploadedFile $profileImageFile): ?User
    {
        // The stored value is the object KEY, not a URL.
        //
        // It used to persist FileUtil::getUrl($path) — a permanent, unsigned,
        // publicly readable link. That baked three problems into a database
        // column: the object had to stay public for the link to work, the row
        // became invalid the moment the storage provider or bucket changed, and
        // the URL leaked to anyone who ever saw the record. Storing the key
        // instead lets UserResource mint a short-lived signed URL per response,
        // so the object stays private and the link expires.
        //
        // The key is generated SERVER-SIDE from the user id and a ULID. The
        // client filename is never used: it is attacker-controlled and is the
        // input to path traversal (`../../`), to overwriting another user's
        // object, and to storing a name the storage backend interprets. Scoping
        // by user id also keeps one user's objects out of another's prefix.
        $path = FileUtil::upload(
            $profileImageFile,
            'profile-images/' . $id
        );

        $user = $this->userRepository->updateProfileImage($id, $path);

        if (empty($user)) {
            throw new BadRequestException('Failed to update profile image.');
        }

        return $user;
    }

}
