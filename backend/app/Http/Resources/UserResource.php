<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $canSeeDetails = $viewer && ($viewer->is($this->resource) || $viewer->can('view', $this->resource));

        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_url' => $this->avatar_url,
            'locale' => $this->locale,
            'is_active' => $this->is_active,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')->values()),
            'phone' => $this->when($canSeeDetails, fn () => $this->phone),
            'google_linked' => $this->when($canSeeDetails, fn () => $this->google_sub !== null),
            'last_login_at' => $this->when($canSeeDetails, fn () => $this->last_login_at?->toIso8601String()),
            'created_at' => $this->when($canSeeDetails, fn () => $this->created_at?->toIso8601String()),
            'can' => $this->when($viewer !== null && $canSeeDetails, fn () => [
                'update' => $viewer->can('update', $this->resource),
                'toggle_active' => $viewer->can('toggleActive', $this->resource),
            ]),
        ];
    }
}
