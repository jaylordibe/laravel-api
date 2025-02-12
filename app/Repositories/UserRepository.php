<?php

namespace App\Repositories;

use App\Constants\AppConstant;
use App\Data\UpdatePasswordData;
use App\Data\UserData;
use App\Data\UserFilterData;
use App\Models\User;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

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
        $user->email = $userData->email;
        $user->username = $userData->username;
        $user->password = Hash::make($password);
        $user->first_name = $userData->firstName;
        $user->last_name = $userData->lastName;
        $user->phone_number = $userData->phoneNumber;
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
        $user->timezone = $userData->timezone;
        $user->phone_number = $userData->phoneNumber;
        $user->birthday = $userData->birthday;
        $user->gender = $userData->gender;
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
        return User::where('email', $email)->exists();
    }

    /**
     * @param string $username
     *
     * @return bool
     */
    public function isUsernameExists(string $username): bool
    {
        return User::where('username', $username)->exists();
    }

    /**
     * @param UserFilterData $userFilterData
     *
     * @return LengthAwarePaginator
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
            $userBuilder->where(function (Builder $searchBuilder) use ($userFilterData) {
                $searchBuilder->where('first_name', 'LIKE', "%{$userFilterData->meta->search}%")
                    ->orWhere('last_name', 'LIKE', "%{$userFilterData->meta->search}%")
                    ->orWhere('username', 'LIKE', "%{$userFilterData->meta->search}%")
                    ->orWhere('email', 'LIKE', "%{$userFilterData->meta->search}%");
            });
        }

        if (!empty($userFilterData->meta->sortField)) {
            $userBuilder->orderBy($userFilterData->meta->sortField, $userFilterData->meta->sortDirection ?? AppConstant::DEFAULT_SORT_DIRECTION);
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
        $user = $this->findById($id);

        if (empty($user)) {
            return false;
        }

        try {
            return (bool) $user->delete();
        } catch (Exception $exception) {
            Log::error("DeleteUserException: {$exception->getMessage()}");
        }

        return false;
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
     * Update profile photo url.
     *
     * @param int $id
     * @param string $profilePhotoUrl
     *
     * @return User|null
     */
    public function updateProfilePhotoUrl(int $id, string $profilePhotoUrl): ?User
    {
        $user = $this->findById($id);

        if (empty($user)) {
            return null;
        }

        $user->profile_photo_url = $profilePhotoUrl;
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
        return User::with($relations)->where('username', $username)->first($columns);
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
        return User::with($relations)->where('email', $email)->first($columns);
    }

}
