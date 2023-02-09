<?php

namespace App\Repositories;

use App\Constants\AppConstant;
use App\Dtos\UserDto;
use App\Models\User;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class UserRepository
{

    /**
     * Find user by id.
     *
     * @param int $id
     * @param array $relations
     * @return User|null
     */
    public function findById(int $id, array $relations = []): ?User
    {
        return User::with($relations)->firstWhere('id', $id);
    }

    /**
     * Save user info.
     *
     * @param UserDto $userDto
     * @param User|null $user
     * @return User|null
     */
    public function save(UserDto $userDto, ?User $user = null): ?User
    {
        $user ??= new User();

        if (empty($user)) {
            $user->created_by = $userDto->getAuthUser()->getId();
        } else {
            $user->updated_by = $userDto->getAuthUser()->getId();
        }

        $user->first_name = $userDto->getFirstName();
        $user->middle_name = $userDto->getMiddleName();
        $user->last_name = $userDto->getLastName();
        $user->email = $userDto->getEmail();
        $user->username = $userDto->getUsername();
        $user->role = $userDto->getRole();
        $user->phone_number = $userDto->getPhoneNumber();
        $user->address = $userDto->getAddress();
        $user->birthday = $userDto->getBirthday();
        $user->profile_image = $userDto->getProfileImage();
        $user->timezone = $userDto->getTimezone();
        $user->save();

        return $user;
    }

    /**
     * @param string $email
     * @return bool
     */
    public function isEmailExists(string $email): bool
    {
        return User::where('email', $email)->exists();
    }

    /**
     * @param string $username
     * @return bool
     */
    public function isUsernameExists(string $username): bool
    {
        return User::where('username', $username)->exists();
    }

    /**
     * @param UserDto $userDto
     * @return LengthAwarePaginator
     */
    public function get(UserDto $userDto): LengthAwarePaginator
    {
        $relations = $userDto->getMeta()->getRelations();
        $sortField = $userDto->getMeta()->getSortField() === AppConstant::DEFAULT_DB_QUERY_SORT_FIELD ? 'first_name' : $userDto->getMeta()->getSortField();
        $sortDirection = $userDto->getMeta()->getSortDirection();
        $limit = $userDto->getMeta()->getLimit();
        $users = User::with($relations);

        if (!empty($userDto->getRole())) {
            $users->where('role', $userDto->getRole());
        }

        return $users->orderBy($sortField, $sortDirection)->paginate($limit);
    }

    /**
     * Delete user.
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $user = $this->findById($id);

        if ($user->isEmpty()) {
            return false;
        }

        try {
            return (bool) $user->delete();
        } catch (Exception $exception) {
            Log::debug('DeleteUserException', [
                'exception' => $exception
            ]);
        }

        return false;
    }
}
