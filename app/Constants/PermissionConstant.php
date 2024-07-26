<?php

namespace App\Constants;

class PermissionConstant extends BaseConstant
{

    const string CREATE_USER = 'CREATE_USER';
    const string READ_USER = 'READ_USER';
    const string UPDATE_USER = 'UPDATE_USER';
    const string DELETE_USER = 'DELETE_USER';

    /**
     * Get permissions by role.
     * Note: Avoid using this function for checking user's permission. Always refer to the database.
     * This is being used for database seeder only.
     *
     * @param string $role
     *
     * @return array
     */
    public static function fromRole(string $role): array
    {
        return match ($role) {
            RoleConstant::SYSTEM_ADMIN => self::asList(),
            default => [],
        };

    }

    /**
     * Get the API guard name.
     *
     * @return string
     */
    public static function getApiGuard(): string
    {
        return 'api';
    }

}
