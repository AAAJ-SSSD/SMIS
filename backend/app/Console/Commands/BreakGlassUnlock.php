<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class BreakGlassUnlock extends Command
{
    protected $signature = 'break-glass:unlock';

    protected $description = 'Clear the break-glass lockout before it expires';

    public function handle(): int
    {
        $email = config('school.super_admin_email');
        $user = $email !== '' ? User::where('email', $email)->first() : null;

        if (! $user) {
            $this->error('No user found for SUPER_ADMIN_EMAIL.');

            return self::FAILURE;
        }

        $user->forceFill(['failed_login_attempts' => 0, 'locked_until' => null])->saveQuietly();

        activity('auth')->event('break_glass_unlocked')->performedOn($user)->log('break_glass_unlocked');

        $this->info('Break-glass account unlocked.');

        return self::SUCCESS;
    }
}
