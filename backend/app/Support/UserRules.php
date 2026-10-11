<?php

namespace App\Support;

use App\Models\User;
use App\Rules\SchoolEmail;
use Illuminate\Validation\Rule;

/**
 * Validation rules for a user record, shared by the admin form and CSV import.
 */
class UserRules
{
    /**
     * @return array<string, mixed>
     */
    public static function for(User $actor, ?User $ignore = null): array
    {
        $unique = Rule::unique('users', 'email');

        if ($ignore) {
            $unique->ignore($ignore->getKey());
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'max:255', 'email:rfc,strict', new SchoolEmail, $unique],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9 ()\-]{6,32}$/'],
            'locale' => ['required', 'string', Rule::in(config('school.locales'))],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string', 'distinct', Rule::in(RoleAssignment::assignableBy($actor))],
        ];
    }
}
