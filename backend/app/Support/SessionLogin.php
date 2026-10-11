<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The single path every login method goes through once a user is verified.
 */
class SessionLogin
{
    public static function complete(Request $request, User $user, string $method): void
    {
        Auth::guard('web')->login($user);

        // Prevent session fixation: new session id + new CSRF token.
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        LoginAudit::succeeded($request, $user, $method);
    }
}
