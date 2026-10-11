<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

/**
 * Accounts are created only by admins/IT. Login never creates a user.
 *
 * Only plain profile fields are mass assignable. Security fields (google_sub,
 * is_active, password, two_factor_secret, lockout state) are always set explicitly.
 */
#[Fillable(['name', 'email', 'phone', 'locale'])]
#[Hidden(['id', 'password', 'two_factor_secret', 'remember_token', 'google_sub', 'failed_login_attempts', 'locked_until'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasUlids, LogsActivity, Notifiable, SoftDeletes;

    /**
     * The ulid column is the public identifier; the numeric id stays internal.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'phone' => 'encrypted',
            'two_factor_secret' => 'encrypted',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
            'failed_login_attempts' => 'integer',
            'password' => 'hashed',
        ];
    }

    protected function setEmailAttribute(?string $value): void
    {
        $this->attributes['email'] = $value === null ? null : Str::lower(trim($value));
    }

    /**
     * Audit trail for profile changes. Phone and secrets are deliberately excluded
     * so personal data does not end up in the log's old/new values.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('users')
            ->logOnly(['name', 'email', 'locale', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    /**
     * Terminate every session this user has, on every device.
     * Requires the database session driver (see config/session.php).
     */
    public function endAllSessions(): void
    {
        DB::table(config('session.table', 'sessions'))->where('user_id', $this->getKey())->delete();

        $this->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
    }

    public function deactivate(): void
    {
        $this->is_active = false;
        $this->save();

        $this->endAllSessions();
    }

    public function activate(): void
    {
        $this->is_active = true;
        $this->save();
    }
}
