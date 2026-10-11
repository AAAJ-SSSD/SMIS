<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Records every login attempt in the audit log (database, access-controlled).
 * Nothing here is written to the application log files.
 */
class LoginAudit
{
    public const LOG_NAME = 'auth';

    public static function succeeded(Request $request, User $user, string $method): void
    {
        activity(self::LOG_NAME)
            ->event('login_succeeded')
            ->causedBy($user)
            ->performedOn($user)
            ->withProperties(static::context($request, $method))
            ->log('login_succeeded');
    }

    public static function failed(Request $request, string $method, string $reason, ?User $user = null, ?string $attemptedEmail = null): void
    {
        $activity = activity(self::LOG_NAME)
            ->event('login_failed')
            ->withProperties(static::context($request, $method) + [
                'reason' => $reason,
                'attempted_email' => $attemptedEmail !== null ? mb_substr($attemptedEmail, 0, 255) : null,
            ]);

        if ($user) {
            $activity->performedOn($user);
        }

        $activity->log('login_failed');
    }

    public static function loggedOut(Request $request, User $user): void
    {
        activity(self::LOG_NAME)
            ->event('logout')
            ->causedBy($user)
            ->performedOn($user)
            ->withProperties(['ip' => $request->ip()])
            ->log('logout');
    }

    /**
     * @return array<string, mixed>
     */
    private static function context(Request $request, string $method): array
    {
        return [
            'method' => $method,
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ];
    }
}
