<?php

namespace App\Constants;

class PermissionConstant extends BaseConstant
{

    const CREATE_USER = 'CREATE_USER';
    const READ_USER = 'READ_USER';
    const UPDATE_USER = 'UPDATE_USER';
    const DELETE_USER = 'DELETE_USER';

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
            RoleConstant::SYSTEM_ADMIN => [
                self::CREATE_USER,
                self::READ_USER,
                self::UPDATE_USER,
                self::DELETE_USER
            ],
            default => [],
        };

    }

}
