<?php

namespace App\Rules;

use App\Support\GoogleLogin;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * When SCHOOL_GOOGLE_DOMAIN is set, every account must live on that domain,
 * otherwise its owner could never sign in.
 */
class SchoolEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (GoogleLogin::domain() !== '' && ! GoogleLogin::emailMatchesDomain(is_string($value) ? $value : null)) {
            $fail('validation.school_email')->translate(['domain' => GoogleLogin::domain()]);
        }
    }
}
