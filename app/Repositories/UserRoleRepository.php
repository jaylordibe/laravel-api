<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class UserRoleRepository
{

    /**
     * Find a role by id.
     *
     * @param int $id
     * @param array $relations
     *
     * @return Role|Model|null
     */
    public function findById(int $id, array $relations = []): Role|Model|null
    {
        return Role::with($relations)->firstWhere('id', $id);
    }

    /**
     * Find a role by name.
     *
     * @param string $name
     * @param string $guardName
     *
     * @return Role|null
     */
    public function findByName(string $name, string $guardName = 'api'): ?Role
    {
        return Role::findByName($name, $guardName);
    }

}
