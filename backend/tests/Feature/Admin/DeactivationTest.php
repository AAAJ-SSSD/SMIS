<?php

use App\Models\User;
use App\Support\Access;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

function fakeSessionFor(User $user, string $id): void
{
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => base64_encode(serialize([])),
        'last_activity' => now()->getTimestamp(),
    ]);
}

it('ends all sessions of a user immediately when they are deactivated', function () {
    $admin = userWithRole(Access::ADMIN);
    $teacher = userWithRole(Access::TEACHER);
    $other = userWithRole(Access::TEACHER);
    fakeSessionFor($teacher, 'teacher-laptop');
    fakeSessionFor($teacher, 'teacher-phone');
    fakeSessionFor($other, 'other-laptop');
    $rememberToken = $teacher->remember_token;

    $this->actingAs($admin)
        ->postJson("/api/users/{$teacher->ulid}/deactivate")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    expect(DB::table('sessions')->where('user_id', $teacher->id)->count())->toBe(0)
        ->and(DB::table('sessions')->where('user_id', $other->id)->count())->toBe(1)
        ->and($teacher->fresh()->is_active)->toBeFalse()
        ->and($teacher->fresh()->remember_token)->not->toBe($rememberToken);
});

it('logs a deactivated user out on their very next request', function () {
    $teacher = userWithRole(Access::TEACHER);
    $this->actingAs($teacher)->getJson('/api/me')->assertOk();

    $teacher->deactivate();

    $this->actingAs($teacher->fresh())
        ->getJson('/api/me')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'account_inactive');
    $this->assertGuest('web');
});

it('ends a real login session when the user is deactivated', function () {
    enableGoogleLogin();
    $teacher = userWithRole(Access::TEACHER, ['email' => 't@'.SCHOOL_DOMAIN]);
    fakeGoogleUser('t@'.SCHOOL_DOMAIN);

    $this->get('/auth/google/callback')->assertRedirect();
    $this->app['session']->driver()->save(); // persist the login session to the sessions table
    expect(DB::table('sessions')->where('user_id', $teacher->id)->count())->toBe(1);

    $teacher->deactivate();

    expect(DB::table('sessions')->where('user_id', $teacher->id)->count())->toBe(0);
});

it('records old and new values when a user is deactivated', function () {
    $admin = userWithRole(Access::ADMIN);
    $teacher = userWithRole(Access::TEACHER);

    $this->actingAs($admin)->postJson("/api/users/{$teacher->ulid}/deactivate")->assertOk();

    $entry = Activity::where('log_name', 'users')->where('event', 'updated')->latest('id')->first();
    expect($entry->causer_id)->toBe($admin->id)
        ->and($entry->properties['old']['is_active'])->toBeTrue()
        ->and($entry->properties['attributes']['is_active'])->toBeFalse();
});

it('can reactivate a user', function () {
    $admin = userWithRole(Access::ADMIN);
    $teacher = User::factory()->inactive()->withRole(Access::TEACHER)->create();

    $this->actingAs($admin)
        ->postJson("/api/users/{$teacher->ulid}/activate")
        ->assertOk()
        ->assertJsonPath('data.is_active', true);
});

it('does not let anyone deactivate themselves', function () {
    $admin = userWithRole(Access::ADMIN);

    $this->actingAs($admin)->postJson("/api/users/{$admin->ulid}/deactivate")->assertForbidden();
});
