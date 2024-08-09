<?php

namespace App\Constants;

class PermissionConstant extends BaseConstant
{

    const string CREATE_USER = 'create_user';
    const string READ_USER = 'read_user';
    const string UPDATE_USER = 'update_user';
    const string DELETE_USER = 'delete_user';

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
