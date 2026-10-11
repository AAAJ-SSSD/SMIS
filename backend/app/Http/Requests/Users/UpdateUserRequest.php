<?php

namespace App\Http\Requests\Users;

use App\Support\UserRules;

class UpdateUserRequest extends StoreUserRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return UserRules::for($this->user(), $this->route('user'));
    }
}
