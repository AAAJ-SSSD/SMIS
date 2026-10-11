<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\BreakGlassLoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\Access;
use App\Support\LoginAudit;
use App\Support\SessionLogin;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

/**
 * Emergency access for the single super-admin (SUPER_ADMIN_EMAIL) using
 * password + TOTP. Locks the account after 5 consecutive failures.
 */
class BreakGlassController extends Controller
{
    private const METHOD = 'break_glass';

    public function store(BreakGlassLoginRequest $request, Google2FA $google2fa): JsonResponse
    {
        $email = strtolower($request->validated('email'));
        $superAdminEmail = config('school.super_admin_email');

        $user = $superAdminEmail !== '' && hash_equals($superAdminEmail, $email)
            ? User::where('email', $email)->first()
            : null;

        if (! $user || $user->password === null || $user->two_factor_secret === null || ! $user->hasRole(Access::SUPER_ADMIN)) {
            // Burn a hash check so timing does not reveal whether the account exists.
            Hash::check($request->validated('password'), Hash::make('break-glass-timing-equaliser'));
            LoginAudit::failed($request, self::METHOD, 'invalid_credentials', attemptedEmail: $email);

            return $this->invalid();
        }

        if ($user->isLocked()) {
            LoginAudit::failed($request, self::METHOD, 'locked', $user, $email);

            return response()->json(['message' => __('auth.locked'), 'code' => 'locked'], 423);
        }

        $passwordOk = Hash::check($request->validated('password'), $user->password);
        $codeOk = $passwordOk && $this->verifyTotp($google2fa, $user, $request->validated('code'));

        if (! $passwordOk || ! $codeOk) {
            $this->registerFailure($user);
            LoginAudit::failed($request, self::METHOD, $user->isLocked() ? 'locked' : 'invalid_credentials', $user, $email);

            return $user->isLocked()
                ? response()->json(['message' => __('auth.locked'), 'code' => 'locked'], 423)
                : $this->invalid();
        }

        if (! $user->is_active) {
            LoginAudit::failed($request, self::METHOD, 'account_inactive', $user, $email);

            return response()->json(['message' => __('auth.account_inactive'), 'code' => 'account_inactive'], 403);
        }

        $user->forceFill(['failed_login_attempts' => 0, 'locked_until' => null])->saveQuietly();

        SessionLogin::complete($request, $user, self::METHOD);

        return response()->json(['data' => new UserResource($user->load('roles'))]);
    }

    private function verifyTotp(Google2FA $google2fa, User $user, string $code): bool
    {
        if (! $google2fa->verifyKey($user->two_factor_secret, $code, 1)) {
            return false;
        }

        // Each code is single-use: reject replays within its validity window.
        return Cache::add('break-glass:totp:'.$user->getKey().':'.$code, true, now()->addMinutes(3));
    }

    private function registerFailure(User $user): void
    {
        $attempts = $user->failed_login_attempts + 1;
        $max = config('school.break_glass.max_attempts');

        $user->forceFill([
            'failed_login_attempts' => $attempts >= $max ? 0 : $attempts,
            'locked_until' => $attempts >= $max ? now()->addMinutes(config('school.break_glass.lock_minutes')) : $user->locked_until,
        ])->saveQuietly();
    }

    private function invalid(): JsonResponse
    {
        return response()->json(['message' => __('auth.failed'), 'code' => 'invalid_credentials'], 422);
    }
}
