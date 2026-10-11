<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Access;
use Spatie\Activitylog\Models\Activity;

/**
 * The audit log is read-only for everyone, including super-admins.
 */
class ActivityPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermissionTo(Access::VIEW_AUDIT_LOG);
    }

    public function view(User $actor, Activity $activity): bool
    {
        return $actor->hasPermissionTo(Access::VIEW_AUDIT_LOG);
    }

    public function create(User $actor): bool
    {
        return false;
    }

    public function update(User $actor, Activity $activity): bool
    {
        return false;
    }

    public function delete(User $actor, Activity $activity): bool
    {
        return false;
    }
}
