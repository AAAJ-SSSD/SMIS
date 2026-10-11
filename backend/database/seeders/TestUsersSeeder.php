<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Access;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * One fake user per role on example.test. Local development only.
 */
class TestUsersSeeder extends Seeder
{
    public const DOMAIN = 'example.test';

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('TestUsersSeeder may only run in the local environment.');
        }

        foreach (Access::ROLES as $role) {
            $user = User::withTrashed()->firstOrNew(['email' => "{$role}@".self::DOMAIN]);

            if (! $user->exists) {
                $user->fill([
                    'name' => 'Test '.str($role)->replace('-', ' ')->title(),
                    'locale' => 'ar',
                ]);
            }

            $user->is_active = true;
            $user->deleted_at = null;
            $user->save();

            $user->syncRoles([$role]);
        }

        User::withTrashed()->firstOrNew(['email' => 'inactive@'.self::DOMAIN])
            ->fill(['name' => 'Test Inactive Teacher', 'locale' => 'en'])
            ->forceFill(['is_active' => false])
            ->save();

        User::where('email', 'inactive@'.self::DOMAIN)->first()?->syncRoles([Access::TEACHER]);
    }
}
