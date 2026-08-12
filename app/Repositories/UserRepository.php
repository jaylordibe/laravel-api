<?php

namespace App\Repositories;

use App\Data\UpdatePasswordData;
use App\Data\UserData;
use App\Data\UserFilterData;
use App\Models\User;
use App\Utils\DatabaseUtil;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserRepository
{

    /**
     * Find user by id.
     *
     * @param int $id
     * @param array $relations
     * @param array $columns
     *
     * @return User|null
     */
    public function findById(int $id, array $relations = [], array $columns = ['*']): ?User
    {
        return User::with($relations)->where('id', $id)->first($columns);
    }

    /**
     * Create user.
     *
     * @param UserData $userData
     * @param string $password
     *
     * @return User|null
     */
    public function create(UserData $userData, string $password): ?User
    {
        $user = new User();
        $user->first_name = $userData->firstName;
        $user->middle_name = $userData->middleName;
        $user->last_name = $userData->lastName;
        $user->username = $userData->username;
        $user->email = $userData->email;
        $user->email_verified_at = $userData->emailVerifiedAt;
        $user->password = Hash::make($password);
        $user->phone_number = $userData->phoneNumber;
        $user->gender = $userData->gender;
        $user->birthdate = $userData->birthdate;
        $user->timezone = $userData->timezone;
        $user->address = $userData->address;
        $user->save();

        return $user->refresh();
    }

    /**
     * Save user.
     *
     * @param UserData $userData
     * @param User|null $user
     *
     * @return User|null
     */
    public function save(UserData $userData, ?User $user = null): ?User
    {
        $user ??= new User();
        $user->first_name = $userData->firstName;
        $user->middle_name = $userData->middleName;
        $user->last_name = $userData->lastName;
        $user->phone_number = $userData->phoneNumber;
        $user->gender = $userData->gender;
        $user->birthdate = $userData->birthdate;
        $user->timezone = $userData->timezone;
        $user->address = $userData->address;
        $user->save();

        return $user->refresh();
    }

    /**
     * @param string $email
     *
     * @return bool
     */
    public function isEmailExists(string $email): bool
    {
        return User::where('email', Str::lower(trim($email)))->exists();
    }

    /**
     * @param string $username
     *
     * @return bool
     */
    public function isUsernameExists(string $username): bool
    {
        return User::where('username', Str::lower(trim($username)))->exists();
    }

    /**
     * @param UserFilterData $userFilterData
     *
     * @return LengthAwarePaginator<User>
     */
    public function getPaginated(UserFilterData $userFilterData): LengthAwarePaginator
    {
        $userBuilder = User::query();

        if (!empty($userFilterData->meta->relations)) {
            $userBuilder->with($userFilterData->meta->relations);
        }

        if (!empty($userFilterData->meta->columns)) {
            $userBuilder->select($userFilterData->meta->columns);
        }

        if (!empty($userFilterData->roles)) {
            $userBuilder->whereHas('roles', function (Builder $roles) use ($userFilterData) {
                $roles->whereIn('name', $userFilterData->roles);
            });
        }

        if (!empty($userFilterData->meta->search)) {
            // ILIKE on PostgreSQL, LIKE on MySQL — see DatabaseUtil for why a
            // plain 'LIKE' here silently became case-sensitive when this template
            // moved to PostgreSQL.
            $operator = DatabaseUtil::caseInsensitiveLikeOperator();
            $pattern = DatabaseUtil::containsPattern($userFilterData->meta->search);

            $userBuilder->where(function (Builder $searchBuilder) use ($operator, $pattern) {
                $searchBuilder->where('first_name', $operator, $pattern)
                    ->orWhere('last_name', $operator, $pattern)
                    ->orWhere('username', $operator, $pattern)
                    ->orWhere('email', $operator, $pattern);
            });
        }

        if (empty($userFilterData->meta->sortField)) {
            $userFilterData->meta->sortField = 'last_name';
        }

        if (!empty($userFilterData->meta->sortField)) {
            $userBuilder->orderBy($userFilterData->meta->sortField, $userFilterData->meta->sortDirection);
        }

        return $userBuilder->paginate($userFilterData->meta->perPage);
    }

    /**
     * Delete user.
     *
     * @param int $id
     *
     * @return bool
     */
    public function delete(int $id): bool
    {
        return User::destroy($id) > 0;
    }

    /**
     * Update username.
     *
     * @param int $id
     * @param string $username
     *
     * @return User|null
     */
    public function updateUsername(int $id, string $username): ?User
    {
        $user = $this->findById($id);

        if (empty($user)) {
            return null;
        }

        // Canonicalise BEFORE comparing. The model lower-cases on write, so the
        // stored value is canonical while the incoming one is raw — comparing them
        // directly meant submitting your OWN username in different case failed the
        // equality check, then matched yourself in the existence check below, and
        // returned "already taken" for a name you already own.
        $username = Str::lower(trim($username));

        if ($username === $user->username) {
            return $user;
        }

        if ($this->isUsernameExists($username)) {
            return null;
        }

        $user->username = $username;
        $user->save();

        return $user;
    }

    /**
     * Update email.
     *
     * @param int $id
     * @param string $email
     *
     * @return User|null
     */
    public function updateEmail(int $id, string $email): ?User
    {
        $user = $this->findById($id);

        if (empty($user)) {
            return null;
        }

        // Canonicalise before comparing — see updateUsername() for why.
        $email = Str::lower(trim($email));

        if ($email === $user->email) {
            return $user;
        }

        if ($this->isEmailExists($email)) {
            return null;
        }

        $user->email = $email;
        $user->save();

        return $user;
    }

    /**
     * Update user password.
     *
     * @param UpdatePasswordData $changePasswordData
     *
     * @return User|null
     */
    public function updatePassword(UpdatePasswordData $changePasswordData): ?User
    {
        $user = $this->findById($changePasswordData->userId);

        if (empty($user)) {
            return null;
        }

        $user->password = Hash::make($changePasswordData->password);
        $user->save();

        return $user;
    }

    /**
     * Update profile image.
     *
     * @param int $id
     * @param string $profileImage
     *
     * @return User|null
     */
    public function updateProfileImage(int $id, string $profileImage): ?User
    {
        $user = $this->findById($id);

        if (empty($user)) {
            return null;
        }

        $user->profile_image = $profileImage;
        $user->save();

        return $user;
    }

    /**
     * Find user by username.
     *
     * @param string $username
     * @param array $relations
     * @param array $columns
     *
     * @return User|null
     */
    public function findByUsername(string $username, array $relations = [], array $columns = ['*']): ?User
    {
        return User::with($relations)->where('username', Str::lower(trim($username)))->first($columns);
    }

    /**
     * Find user by email.
     *
     * @param string $email
     * @param array $relations
     * @param array $columns
     *
     * @return User|null
     */
    public function findByEmail(string $email, array $relations = [], array $columns = ['*']): ?User
    {
        return User::with($relations)->where('email', Str::lower(trim($email)))->first($columns);
    }

}
