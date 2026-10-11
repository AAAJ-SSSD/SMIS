<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Access;
use Illuminate\Database\Seeder;

/**
 * Creates (or re-enables) the super-admin from SUPER_ADMIN_EMAIL.
 * The break-glass password and TOTP secret are set separately with
 * `php artisan break-glass:setup`, never from env or seed data.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('school.super_admin_email');

        if ($email === '') {
            $this->command?->warn('SUPER_ADMIN_EMAIL is empty; no super-admin seeded.');

            return;
        }

        $user = User::withTrashed()->firstOrNew(['email' => $email]);

        if (! $user->exists) {
            $user->fill(['name' => 'Super Admin', 'locale' => 'ar']);
        }

        $user->is_active = true;
        $user->deleted_at = null;
        $user->save();

        $user->assignRole(Access::SUPER_ADMIN);
    }
}
