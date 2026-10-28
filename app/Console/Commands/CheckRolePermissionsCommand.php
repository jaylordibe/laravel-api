<?php

namespace App\Console\Commands;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CheckRolePermissionsCommand extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-role-permissions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fail when the database roles and permissions differ from UserPermission::fromUserRole';

    /**
     * Execute the console command.
     *
     * Read-only. Run it after the permission sync on every deploy (DEPLOYMENT.md): a permission missing from
     * the database silently denies everyone who should have it — the gate throws, or answers 403 —
     * and nothing else would say so until someone reports it.
     *
     * @return int
     */
    public function handle(): int
    {
        $guardName = UserPermission::getApiGuardName();
        $problems = [];

        $expectedPermissionNames = collect(UserPermission::cases())
            ->map(fn (UserPermission $userPermission): string => $userPermission->value);
        $databasePermissionNames = Permission::where('guard_name', $guardName)->pluck('name');

        foreach ($expectedPermissionNames->diff($databasePermissionNames) as $permissionName) {
            $problems[] = "Permission {$permissionName} is missing from the database.";
        }

        foreach ($databasePermissionNames->diff($expectedPermissionNames) as $permissionName) {
            $problems[] = "Permission {$permissionName} is in the database but not in UserPermission.";
        }

        foreach (UserRole::cases() as $userRole) {
            $role = Role::where('name', $userRole->value)->where('guard_name', $guardName)->first();

            if (empty($role)) {
                $problems[] = "Role {$userRole->value} is missing from the database.";
                continue;
            }

            $expectedRolePermissionNames = collect(UserPermission::fromUserRole($userRole))
                ->map(fn (UserPermission $userPermission): string => $userPermission->value);
            $databaseRolePermissionNames = $role->permissions->pluck('name');

            foreach ($expectedRolePermissionNames->diff($databaseRolePermissionNames) as $permissionName) {
                $problems[] = "Role {$userRole->value} lacks {$permissionName}.";
            }

            foreach ($databaseRolePermissionNames->diff($expectedRolePermissionNames) as $permissionName) {
                $problems[] = "Role {$userRole->value} holds {$permissionName}, which its mapping does not grant.";
            }
        }

        if (!empty($problems)) {
            foreach ($problems as $problem) {
                $this->error($problem);
            }

            $this->error('Roles and permissions differ from the code. Run the permission sync: `php artisan db:seed --class=PermissionsSeeder --force`.');

            return self::FAILURE;
        }

        $this->info('Roles and permissions match UserPermission::fromUserRole.');

        return self::SUCCESS;
    }

}
