<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\DevLoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\LoginAudit;
use App\Support\SessionLogin;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Only routed when APP_ENV=local and DEV_LOGIN_ENABLED=true (see App\Support\DevLogin).
 * Limited to the seeded example.test users, so it can never log in as a real account.
 */
class DevLoginController extends Controller
{
    private const METHOD = 'dev';

    public function index(): AnonymousResourceCollection
    {
        $users = User::with('roles')
            ->where('email', 'like', '%@'.TestUsersSeeder::DOMAIN)
            ->orderBy('name')
            ->get();

        return UserResource::collection($users);
    }

    public function store(DevLoginRequest $request): JsonResponse
    {
        $user = User::where('ulid', $request->validated('ulid'))
            ->where('email', 'like', '%@'.TestUsersSeeder::DOMAIN)
            ->first();

        if (! $user) {
            LoginAudit::failed($request, self::METHOD, 'not_registered');

            return response()->json(['message' => __('auth.not_registered'), 'code' => 'not_registered'], 422);
        }

        if (! $user->is_active) {
            LoginAudit::failed($request, self::METHOD, 'account_inactive', $user, $user->email);

            return response()->json(['message' => __('auth.account_inactive'), 'code' => 'account_inactive'], 403);
        }

        SessionLogin::complete($request, $user, self::METHOD);

        return response()->json(['data' => new UserResource($user->load('roles'))]);
    }
}
