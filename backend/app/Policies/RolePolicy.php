<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Access;
use Spatie\Permission\Models\Role;

/**
 * Roles are fixed in Phase 1 (seeded); they can be listed but not changed via the API.
 */
class RolePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermissionTo(Access::MANAGE_USERS) || $actor->hasPermissionTo(Access::MANAGE_ROLES);
    }

    public function view(User $actor, Role $role): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, Role $role): bool
    {
        return false;
    }

    public function delete(User $actor, Role $role): bool
    {
        return false;
    }
}
