<?php

namespace App\Enums;

use UnitEnum;

enum UserPermission: string
{

    case CREATE_USER = 'create_user';
    case READ_USER = 'read_user';
    case UPDATE_USER = 'update_user';
    case DELETE_USER = 'delete_user';

    /**
     * @param UserRole $userRole
     *
     * @return array<UnitEnum>
     */
    public static function fromUserRole(UserRole $userRole): array
    {
        return match ($userRole) {
            UserRole::SYSTEM_ADMIN, UserRole::APP_ADMIN => self::cases(),
            default => [],
        };
    }

    /**
     * Get the API guard name.
     *
     * @return string
     */
    public static function getApiGuardName(): string
    {
        return 'api';
    }

}
