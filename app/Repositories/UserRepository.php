<?php

namespace App\Repositories;

use App\Constants\AppConstant;
use App\Constants\RoleConstant;
use App\Dtos\UserDto;
use App\Dtos\UserFilterDto;
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
     * Save user.
     *
     * @param UserDto $userDto
     * @param User|null $user
     *
     * @return User|null
     */
    public function save(UserDto $userDto, ?User $user = null): ?User
    {
        $user ??= new User();

        if (!$user->exists) {
            $user->email = $userDto->getEmail();
            $user->username = $userDto->getUsername();
            $user->password = Hash::make($userDto->getPassword());
        }

        $user->first_name = $userDto->getFirstName();
        $user->middle_name = $userDto->getMiddleName();
        $user->last_name = $userDto->getLastName();
        $user->timezone = $userDto->getTimezone();
        $user->phone_number = $userDto->getPhoneNumber();
        $user->birthday = $userDto->getBirthday();
        $user->save();

        return $user;
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
     * @param UserFilterDto $userFilterDto
     *
     * @return LengthAwarePaginator
     */
    public function get(UserFilterDto $userFilterDto): LengthAwarePaginator
    {
        $relations = $userFilterDto->getMeta()->getRelations();
        $sortField = $userFilterDto->getMeta()->getSortField() === AppConstant::DEFAULT_DB_QUERY_SORT_FIELD ? 'first_name' : $userFilterDto->getMeta()->getSortField();
        $sortDirection = $userFilterDto->getMeta()->getSortDirection();
        $limit = $userFilterDto->getMeta()->getLimit();
        $searchQuery = $userFilterDto->getMeta()->getSearchQuery();

        $users = User::with($relations);

        if (!empty($userFilterDto->getRoles())) {
            $users->whereHas('roles', function (Builder $roles) use ($userFilterDto) {
                $roles->whereIn('name', $userFilterDto->getRoles());
            });
        }

        if (!empty($searchQuery)) {
            $users->where(function (Builder $searchBuilder) use ($searchQuery) {
                $searchBuilder->where('first_name', 'LIKE', "%{$searchQuery}%")
                    ->orWhere('last_name', 'LIKE', "%{$searchQuery}%")
                    ->orWhere('username', 'LIKE', "%{$searchQuery}%")
                    ->orWhere('email', 'LIKE', "%{$searchQuery}%");
            });
        }

        return $users->orderBy($sortField, $sortDirection)->paginate($limit);
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

        if ($this->isUsernameExists($username)) {
            return null;
        }

        $user->username = $username;
        $user->updated_by = Auth::user()->id;
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

        if ($this->isEmailExists($email)) {
            return null;
        }

        $user->email = $email;
        $user->updated_by = Auth::user()->id;
        $user->save();

        return $user;
    }

}
