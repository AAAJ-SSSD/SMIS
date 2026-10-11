<?php

namespace App\Http\Controllers;

use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Requests\Users\UserIndexRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function __construct(private readonly UserManager $users) {}

    public function index(UserIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $users = User::query()
            ->with('roles')
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn (Builder $q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->when($filters['role'] ?? null, fn (Builder $query, string $role) => $query->role($role))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('is_active', $status === 'active'))
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString();

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->users->create(
            $request->safe()->only(['name', 'email', 'phone', 'locale']),
            $request->validated('roles'),
            $request->user(),
        );

        return (new UserResource($user->load('roles')))->response()->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        Gate::authorize('view', $user);

        return new UserResource($user->load('roles'));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $user = $this->users->update(
            $user,
            $request->safe()->only(['name', 'email', 'phone', 'locale']),
            $request->validated('roles'),
            $request->user(),
        );

        return new UserResource($user->load('roles'));
    }

    public function activate(User $user): UserResource
    {
        Gate::authorize('toggleActive', $user);

        $user->activate();

        return new UserResource($user->load('roles'));
    }

    /**
     * Deactivation immediately ends every session the user has.
     */
    public function deactivate(User $user): UserResource
    {
        Gate::authorize('toggleActive', $user);

        $user->deactivate();

        return new UserResource($user->load('roles'));
    }
}
