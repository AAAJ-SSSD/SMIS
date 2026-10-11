<?php

namespace App\Support;

use App\Models\User;

/**
 * Who may hand out which roles.
 *
 *  - super-admin: every role
 *  - manage-roles: every role except super-admin
 *  - otherwise: only non-privileged roles (teacher, student, parent, reserved roles)
 */
class RoleAssignment
{
    /**
     * @return list<string>
     */
    public static function assignableBy(User $actor): array
    {
        if ($actor->hasRole(Access::SUPER_ADMIN)) {
            return Access::ROLES;
        }

        if ($actor->hasPermissionTo(Access::MANAGE_ROLES)) {
            return array_values(array_diff(Access::ROLES, [Access::SUPER_ADMIN]));
        }

        return array_values(array_diff(Access::ROLES, Access::PRIVILEGED_ROLES));
    }

    /**
     * An actor may only manage a user whose current roles they could have assigned.
     */
    public static function canManageRolesOf(User $actor, User $target): bool
    {
        return $target->getRoleNames()->diff(static::assignableBy($actor))->isEmpty();
    }
}
