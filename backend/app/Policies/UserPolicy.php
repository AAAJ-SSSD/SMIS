<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Access;
use App\Support\RoleAssignment;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermissionTo(Access::MANAGE_USERS);
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->hasPermissionTo(Access::MANAGE_USERS);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermissionTo(Access::MANAGE_USERS);
    }

    /**
     * Nobody may edit an account holding roles they could not have assigned
     * (e.g. an admin cannot edit IT staff or the super-admin).
     */
    public function update(User $actor, User $target): bool
    {
        return $actor->hasPermissionTo(Access::MANAGE_USERS)
            && RoleAssignment::canManageRolesOf($actor, $target);
    }

    /**
     * Activate / deactivate. Nobody can deactivate themselves.
     */
    public function toggleActive(User $actor, User $target): bool
    {
        return $actor->isNot($target) && $this->update($actor, $target);
    }

    public function import(User $actor): bool
    {
        return $actor->hasPermissionTo(Access::IMPORT_USERS);
    }
}
