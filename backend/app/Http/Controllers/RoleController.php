<?php

namespace App\Http\Controllers;

use App\Support\RoleAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * All roles, flagged with whether the current user may assign them.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Role::class);

        $assignable = RoleAssignment::assignableBy($request->user());

        $roles = Role::with('permissions')->orderBy('id')->get()->map(fn (Role $role) => [
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('name')->values(),
            'assignable' => in_array($role->name, $assignable, true),
        ]);

        return response()->json(['data' => $roles]);
    }
}
