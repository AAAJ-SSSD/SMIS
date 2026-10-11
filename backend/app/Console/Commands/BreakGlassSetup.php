<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Access;
use Illuminate\Console\Command;
use PragmaRX\Google2FA\Google2FA;

use function Laravel\Prompts\password;

/**
 * Sets the break-glass password and a fresh TOTP secret for the SUPER_ADMIN_EMAIL
 * account. Run on the server by IT; the secret is shown once.
 */
class BreakGlassSetup extends Command
{
    protected $signature = 'break-glass:setup';

    protected $description = 'Set the break-glass super-admin password and TOTP secret';

    public function handle(Google2FA $google2fa): int
    {
        $email = config('school.super_admin_email');
        $user = $email !== '' ? User::where('email', $email)->first() : null;

        if (! $user || ! $user->hasRole(Access::SUPER_ADMIN)) {
            $this->error('No super-admin found for SUPER_ADMIN_EMAIL. Run `php artisan db:seed` first.');

            return self::FAILURE;
        }

        $password = password(
            label: 'New break-glass password',
            required: true,
            validate: fn (string $value) => mb_strlen($value) < 16 ? 'Use at least 16 characters.' : null,
        );

        if (password(label: 'Confirm password', required: true) !== $password) {
            $this->error('Passwords do not match.');

            return self::FAILURE;
        }

        $secret = $google2fa->generateSecretKey(32);

        $user->forceFill([
            'password' => $password,
            'two_factor_secret' => $secret,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ])->save();

        activity('auth')->event('break_glass_configured')->performedOn($user)->log('break_glass_configured');

        $this->info('Break-glass configured. Add this to an authenticator app now; it is not shown again:');
        $this->line('Secret: '.$secret);
        $this->line('URI:    '.$google2fa->getQRCodeUrl(config('app.name'), $user->email, $secret));

        return self::SUCCESS;
    }
}
