<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\DevLogin;
use App\Support\GoogleLogin;
use App\Support\LoginAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class SessionController extends Controller
{
    /**
     * Public: tells the login page which sign-in options to show.
     */
    public function config(): JsonResponse
    {
        return response()->json([
            'google_enabled' => GoogleLogin::isActive(),
            'dev_login_enabled' => DevLogin::enabled(),
            'school_domain' => GoogleLogin::domain() ?: null,
        ]);
    }

    public function logout(Request $request): Response
    {
        if ($user = $request->user()) {
            LoginAudit::loggedOut($request, $user);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
