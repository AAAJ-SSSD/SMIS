<?php

namespace App\Support;

/**
 * Single source of truth for role and permission names.
 */
final class Access
{
    public const SUPER_ADMIN = 'super-admin';

    public const IT = 'it';

    public const ADMIN = 'admin';

    public const HOD = 'hod';

    public const TEACHER = 'teacher';

    public const STUDENT = 'student';

    public const PARENT = 'parent';

    // Reserved for later phases: roles exist, no screens yet.
    public const GUARD = 'guard';

    public const DRIVER = 'driver';

    public const HELPER = 'helper';

    public const MANAGE_USERS = 'manage-users';

    public const MANAGE_ROLES = 'manage-roles';

    public const MANAGE_GRADING = 'manage-grading';

    public const VIEW_AUDIT_LOG = 'view-audit-log';

    public const IMPORT_USERS = 'import-users';

    public const ROLES = [
        self::SUPER_ADMIN, self::IT, self::ADMIN, self::HOD, self::TEACHER,
        self::STUDENT, self::PARENT, self::GUARD, self::DRIVER, self::HELPER,
    ];

    public const PERMISSIONS = [
        self::MANAGE_USERS, self::MANAGE_ROLES, self::MANAGE_GRADING,
        self::VIEW_AUDIT_LOG, self::IMPORT_USERS,
    ];

    /**
     * Roles that grant administrative power; assigning them requires manage-roles.
     */
    public const PRIVILEGED_ROLES = [self::SUPER_ADMIN, self::IT, self::ADMIN, self::HOD];

    /**
     * @return array<string, list<string>>
     */
    public static function rolePermissions(): array
    {
        return [
            self::SUPER_ADMIN => self::PERMISSIONS,
            self::IT => [self::MANAGE_USERS, self::MANAGE_ROLES, self::IMPORT_USERS, self::VIEW_AUDIT_LOG],
            self::ADMIN => [self::MANAGE_USERS, self::IMPORT_USERS, self::VIEW_AUDIT_LOG, self::MANAGE_GRADING],
            self::HOD => [self::MANAGE_GRADING],
            self::TEACHER => [],
            self::STUDENT => [],
            self::PARENT => [],
            self::GUARD => [],
            self::DRIVER => [],
            self::HELPER => [],
        ];
    }
}
