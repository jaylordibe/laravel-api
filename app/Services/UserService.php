<?php

namespace App\Services;

use App\Constants\UserConstant;
use App\Data\CreateUserData;
use App\Data\ResetPasswordData;
use App\Data\SignUpUserData;
use App\Data\UpdateEmailData;
use App\Data\UpdatePasswordData;
use App\Data\UserData;
use App\Data\UserFilterData;
use App\Enums\UserRole;
use App\Exceptions\BadRequestException;
use App\Exceptions\InvalidVerificationLinkException;
use App\Models\User;
use App\Notifications\EmailChangeRequestedNotification;
use App\Notifications\VerifyEmailChangeNotification;
use App\Repositories\UserRepository;
use App\Repositories\UserRoleRepository;
use App\Utils\AppUtil;
use App\Utils\FileUtil;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
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
     * Sign the user out of every session and cancel any pending email change.
     *
     * Signing out everywhere is what an owner does on suspecting someone else is in the account, and an email
     * change that someone else started would otherwise outlive it.
     *
     * @param User $user
     *
     * @return void
     * @throws Throwable
     */
    public function signOutEverywhere(User $user): void
    {
        // Revoke and clear under the row lock an email change request takes, so a request in progress is
        // cleared rather than half-applied. One that authenticated before the revoke but locks the row after
        // it can still save a new change; that needs the current password, and a password reset (one atomic
        // UPDATE) is the remedy the warning email gives.
        DB::transaction(function () use ($user) {
            $lockedUser = $this->userRepository->findByIdForUpdate($user->id) ?? $user;
            $this->revokeAllUserTokens($lockedUser);
            $this->userRepository->clearPendingEmail($lockedUser);
        });
    }

    /**
     * Sign up a new user and send the email verification link.
     *
     * A uniqueness race answers 400 like the validation it slipped past, never a raw database error.
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

        try {
            // In a transaction so a constraint violation rolls back to a savepoint when nested in one.
            $user = DB::transaction(fn () => $this->userRepository->create($userData, $signUpUserData->password));
        } catch (UniqueConstraintViolationException) {
            // Lost a race the request's unique rule could not see: the same email signing up twice at once,
            // or two sign-ups generating the same username.
            throw new BadRequestException($this->isEmailExists($signUpUserData->email)
                ? 'The email has already been taken.'
                : 'Sign up failed. Please try again.');
        }

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
                // updatePassword also discards a pending email change, which recovery must stop.
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
     * @throws InvalidVerificationLinkException
     */
    public function verifyEmail(int $userId, string $hash): ?User
    {
        $user = $this->userRepository->findById($userId);

        if (empty($user) || !hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            throw new InvalidVerificationLinkException();
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
     * Generate an unused username from a name or an email's local part.
     *
     * The result always satisfies UpdateUsernameRequest's format: lower-case letters and digits, at least
     * three characters (an all-symbol local part such as `...@example.com` used to give an EMPTY username)
     * and at most UserConstant::MAX_USERNAME_LENGTH including the numeric suffix added on a collision.
     *
     * @param string $text
     * @param int $count
     *
     * @return string
     */
    public function generateUsername(string $text, int $count = 0): string
    {
        $base = Str::lower(str_replace(' ', '', $text));

        if (AppUtil::isValidEmail($base)) {
            $base = Str::before($base, '@');
        }

        $base = preg_replace('/[^a-z0-9]/', '', $base);

        if (strlen($base) < 3) {
            $base = 'user' . $base;
        }

        $suffix = empty($count) ? '' : (string) $count;
        $username = substr($base, 0, UserConstant::MAX_USERNAME_LENGTH - strlen($suffix)) . $suffix;

        if ($this->isUsernameExists($username)) {
            return $this->generateUsername($text, ++$count);
        }

        return $username;
    }

    /**
     * Update username.
     *
     * Uniqueness is validated in UpdateUsernameRequest; the unique index settles a race between two
     * requests for the same name, and the loser gets the same answer as a validation failure.
     *
     * @param int $id
     * @param string $username
     *
     * @return User|null
     * @throws BadRequestException
     */
    public function updateUsername(int $id, string $username): ?User
    {
        $this->getById($id);

        try {
            // In a transaction so a constraint violation rolls back to a savepoint when nested in one.
            $user = DB::transaction(fn () => $this->userRepository->updateUsername($id, $username));
        } catch (UniqueConstraintViolationException) {
            $user = null;
        }

        if (empty($user)) {
            throw new BadRequestException('The username has already been taken.');
        }

        return $user;
    }

    /**
     * Request an email change: confirm the current password, then send a confirmation link to the new
     * address and a notice to the current one. The current email stays the account's email, for sign-in
     * and recovery, until the change is confirmed.
     *
     * Each request replaces the pending one and its token, so every older link stops working. Submitting
     * the current email cancels any pending change. Uniqueness is checked only after the password, so a
     * bearer token alone cannot probe which addresses have accounts.
     *
     * @param UpdateEmailData $updateEmailData
     *
     * @return User
     * @throws BadRequestException
     */
    public function requestEmailChange(UpdateEmailData $updateEmailData): User
    {
        $email = Str::lower(trim($updateEmailData->email));

        // The row stays locked from the password check to the save, so a password change or reset that
        // commits first is seen here (the check fails), and one that commits after clears what was saved.
        [$user, $token] = DB::transaction(function () use ($updateEmailData, $email) {
            $user = $this->userRepository->findByIdForUpdate($updateEmailData->userId);

            if (empty($user)) {
                throw new BadRequestException('User not found.');
            }

            // Step-up: a bearer token alone must not be able to move the account to another inbox.
            if (!Hash::check($updateEmailData->currentPassword, $user->password)) {
                throw new BadRequestException('Current password is incorrect.');
            }

            if ($email === $user->email) {
                return [$this->userRepository->clearPendingEmail($user), null];
            }

            if ($this->userRepository->isEmailExists($email)) {
                throw new BadRequestException('The email has already been taken.');
            }

            $token = Str::random(64);
            $expiresAt = now()->addMinutes((int) config('auth.verification.expire'));

            return [$this->userRepository->savePendingEmail($user, $email, hash('sha256', $token), $expiresAt), $token];
        });

        if ($token === null) {
            return $user;
        }

        // The link opens the client app, which posts the token back to /api/email/change/confirm — like the
        // password reset link. A GET that changed the email would be confirmed by any mail scanner that
        // prefetches links.
        $url = config('custom.app_frontend_url') . '/confirm-email-change?' . http_build_query(['userId' => $user->id, 'token' => $token]);
        Notification::route('mail', $email)->notify(new VerifyEmailChangeNotification($url));
        // An early warning to the address that still controls recovery: a password reset cancels the change.
        // Routed to the address captured NOW: a queued notification on the User would be sent to whatever the
        // email is at delivery time, which after a quick confirmation is the new inbox.
        Notification::route('mail', $user->email)->notify(new EmailChangeRequestedNotification($email));

        return $user;
    }

    /**
     * Cancel a pending email change, which invalidates its link.
     *
     * @param int $id
     *
     * @return User
     * @throws BadRequestException
     */
    public function cancelEmailChange(int $id): User
    {
        return $this->userRepository->clearPendingEmail($this->getById($id));
    }

    /**
     * Confirm a pending email change with the token from the emailed link, then sign out every session.
     *
     * The token binds the change to one user and one request: it is random, only its hash is stored, a new
     * request, a cancellation or a password change overwrites it, and it expires. A wrong user id, token or
     * expired request all answer the same. The row is locked so two confirmations cannot both apply it. The
     * address is re-checked because another account may have taken it since the request; the unique index
     * settles any race that check cannot see.
     *
     * Every session is revoked because whoever holds the account's tokens is not proven to be whoever
     * controls the new inbox — the user signs in again with the new address.
     *
     * @param int $userId
     * @param string $token
     *
     * @return User
     * @throws BadRequestException
     */
    public function confirmEmailChange(int $userId, string $token): User
    {
        $invalidLinkMessage = 'This email change link is invalid or has expired.';

        // Checked once without a lock, so a wrong or guessed token from this public route never holds the
        // user's row; then again under the lock, since a new request or a cancellation may have replaced it.
        if (!$this->isValidEmailChangeToken($this->userRepository->findById($userId), $token)) {
            throw new BadRequestException($invalidLinkMessage);
        }

        try {
            return DB::transaction(function () use ($userId, $token, $invalidLinkMessage) {
                $user = $this->userRepository->findByIdForUpdate($userId);

                if (!$this->isValidEmailChangeToken($user, $token) || $this->userRepository->isEmailExists($user->pending_email)) {
                    throw new BadRequestException($invalidLinkMessage);
                }

                $user = $this->userRepository->applyPendingEmail($user);
                $this->revokeAllUserTokens($user);

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            throw new BadRequestException($invalidLinkMessage);
        }
    }

    /**
     * Whether the token matches the user's pending email change and that change has not expired.
     *
     * @param User|null $user
     * @param string $token
     *
     * @return bool
     */
    private function isValidEmailChangeToken(?User $user, string $token): bool
    {
        return !empty($user)
            && !empty($user->pending_email)
            && !empty($user->pending_email_token)
            && hash_equals($user->pending_email_token, hash('sha256', $token))
            && !empty($user->pending_email_expires_at)
            && $user->pending_email_expires_at->isFuture();
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
        // The repository's password write also discards any pending email change, in the same statement.
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
