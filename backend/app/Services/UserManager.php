<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creating and updating users, shared by the admin form and the CSV import.
 */
class UserManager
{
    /**
     * @param  array{name: string, email: string, phone?: ?string, locale: string}  $profile
     * @param  list<string>  $roles
     */
    public function create(array $profile, array $roles, User $actor): User
    {
        return DB::transaction(function () use ($profile, $roles, $actor) {
            $user = new User($profile);
            $user->is_active = true;
            $user->save();

            $this->syncRoles($user, $roles, $actor);

            return $user;
        });
    }

    /**
     * @param  array{name: string, email: string, phone?: ?string, locale: string}  $profile
     * @param  list<string>  $roles
     */
    public function update(User $user, array $profile, array $roles, User $actor): User
    {
        return DB::transaction(function () use ($user, $profile, $roles, $actor) {
            $emailChanged = $user->email !== strtolower(trim($profile['email']));

            $user->fill($profile);

            // A new email means a new person may sign in: unbind the old Google identity.
            if ($emailChanged) {
                $user->google_sub = null;
            }

            $user->save();

            $this->syncRoles($user, $roles, $actor);

            if ($emailChanged) {
                $user->endAllSessions();
            }

            return $user;
        });
    }

    /**
     * Role changes are audited explicitly with old and new values.
     *
     * @param  list<string>  $roles
     */
    private function syncRoles(User $user, array $roles, User $actor): void
    {
        $old = $user->getRoleNames()->sort()->values()->all();
        $new = collect($roles)->unique()->sort()->values()->all();

        if ($old === $new) {
            return;
        }

        $user->syncRoles($new);

        activity('users')
            ->event('roles_updated')
            ->causedBy($actor)
            ->performedOn($user)
            ->withProperties(['old' => ['roles' => $old], 'attributes' => ['roles' => $new]])
            ->log('roles_updated');
    }
}
