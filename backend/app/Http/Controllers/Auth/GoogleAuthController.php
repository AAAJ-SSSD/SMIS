<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\GoogleLogin;
use App\Support\LoginAudit;
use App\Support\SessionLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

class GoogleAuthController extends Controller
{
    private const METHOD = 'google';

    /**
     * Send the browser to Google. Socialite stores a random `state` in the
     * session and verifies it on callback (CSRF protection for OAuth).
     */
    public function redirect(): SymfonyRedirect
    {
        abort_unless(GoogleLogin::isActive(), 404);

        return Socialite::driver('google')
            ->with([
                'hd' => GoogleLogin::domain(), // UI hint only; enforced on callback
                'prompt' => 'select_account',
            ])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless(GoogleLogin::isActive(), 404);

        if ($request->filled('error')) {
            return $this->fail($request, 'google_cancelled');
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            return $this->fail($request, 'invalid_state');
        } catch (Throwable) {
            return $this->fail($request, 'google_error');
        }

        $raw = $googleUser->getRaw();
        $email = strtolower(trim((string) $googleUser->getEmail()));
        $sub = (string) $googleUser->getId();

        if ($sub === '' || $email === '') {
            return $this->fail($request, 'google_error', attemptedEmail: $email ?: null);
        }

        if (($raw['email_verified'] ?? false) !== true) {
            return $this->fail($request, 'email_not_verified', attemptedEmail: $email);
        }

        // Both the Workspace "hd" claim and the email's domain must be the school domain.
        $hd = strtolower((string) ($raw['hd'] ?? ''));
        if ($hd !== GoogleLogin::domain() || ! GoogleLogin::emailMatchesDomain($email)) {
            return $this->fail($request, 'wrong_domain', attemptedEmail: $email);
        }

        $user = $this->findUser($sub, $email);

        if (! $user) {
            return $this->fail($request, 'not_registered', attemptedEmail: $email);
        }

        // Email matches but the account is already bound to a different Google identity.
        if ($user->google_sub !== null && $user->google_sub !== $sub) {
            return $this->fail($request, 'identity_mismatch', $user, $email);
        }

        if (! $user->is_active) {
            return $this->fail($request, 'account_inactive', $user, $email);
        }

        $this->syncGoogleProfile($user, $googleUser, $sub);

        SessionLogin::complete($request, $user, self::METHOD);

        return redirect()->away(config('school.frontend_url').'/');
    }

    /**
     * By google_sub first; otherwise by the Google-verified email.
     * Never creates a user.
     */
    private function findUser(string $sub, string $email): ?User
    {
        return User::where('google_sub', $sub)->first()
            ?? User::where('email', $email)->first();
    }

    private function syncGoogleProfile(User $user, SocialiteUser $googleUser, string $sub): void
    {
        $user->google_sub = $sub;

        $avatar = $googleUser->getAvatar();
        if (is_string($avatar) && str_starts_with($avatar, 'https://')) {
            $user->avatar_url = $avatar;
        }

        $user->saveQuietly();
    }

    private function fail(Request $request, string $reason, ?User $user = null, ?string $attemptedEmail = null): RedirectResponse
    {
        LoginAudit::failed($request, self::METHOD, $reason, $user, $attemptedEmail);

        return redirect()->away(config('school.frontend_url').'/login?error='.$reason);
    }
}
