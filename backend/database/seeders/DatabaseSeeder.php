<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SuperAdminSeeder::class,
        ]);

        // Fake users never reach any environment other than local.
        if (app()->environment('local')) {
            $this->call(TestUsersSeeder::class);
        }
    }
}
