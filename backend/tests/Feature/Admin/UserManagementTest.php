<?php

use App\Models\User;
use App\Support\Access;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

beforeEach(fn () => $this->admin = userWithRole(Access::ADMIN));

it('lists users with search and role/status filters', function () {
    userWithRole(Access::TEACHER, ['name' => 'Sara Teacher', 'email' => 'sara@example.test']);
    userWithRole(Access::STUDENT, ['name' => 'Omar Student', 'email' => 'omar@example.test']);
    User::factory()->inactive()->withRole(Access::TEACHER)->create(['name' => 'Old Teacher']);

    $this->actingAs($this->admin)->getJson('/api/users?search=sara')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Sara Teacher');

    $this->actingAs($this->admin)->getJson('/api/users?role=teacher')
        ->assertOk()->assertJsonCount(2, 'data');

    $this->actingAs($this->admin)->getJson('/api/users?role=teacher&status=inactive')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Old Teacher');
});

it('treats search wildcards literally', function () {
    userWithRole(Access::TEACHER, ['name' => 'Plain']);

    $this->actingAs($this->admin)->getJson('/api/users?search=%25')->assertOk()->assertJsonCount(0, 'data');
});

it('creates a user with roles and audits it', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/users', [
            'name' => 'New Teacher', 'email' => ' New.Teacher@Example.test ', 'locale' => 'en',
            'roles' => [Access::TEACHER], 'phone' => '+971 50 123 4567',
        ])
        ->assertCreated()
        ->assertJsonPath('data.email', 'new.teacher@example.test')
        ->assertJsonPath('data.roles', [Access::TEACHER]);

    $user = User::where('email', 'new.teacher@example.test')->firstOrFail();
    expect($user->phone)->toBe('+971 50 123 4567')
        ->and(DB::table('users')->where('id', $user->id)->value('phone'))->not->toContain('123 4567'); // encrypted at rest

    expect(Activity::where('subject_id', $user->id)->pluck('event')->all())->toContain('created', 'roles_updated');
});

it('does not put phone numbers into the audit log', function () {
    $this->actingAs($this->admin)->postJson('/api/users', [
        'name' => 'P', 'email' => 'p@example.test', 'locale' => 'ar', 'roles' => [Access::STUDENT], 'phone' => '0501112222',
    ])->assertCreated();

    expect(Activity::all()->toJson())->not->toContain('0501112222');
});

it('requires the school domain when SCHOOL_GOOGLE_DOMAIN is set', function () {
    enableGoogleLogin();

    $this->actingAs($this->admin)
        ->postJson('/api/users', ['name' => 'X', 'email' => 'x@gmail.com', 'locale' => 'ar', 'roles' => [Access::TEACHER]])
        ->assertUnprocessable()->assertJsonValidationErrors('email');

    $this->actingAs($this->admin)
        ->postJson('/api/users', ['name' => 'X', 'email' => 'x@'.SCHOOL_DOMAIN, 'locale' => 'ar', 'roles' => [Access::TEACHER]])
        ->assertCreated();
});

it('rejects duplicate emails, including soft-deleted users', function () {
    $existing = userWithRole(Access::TEACHER, ['email' => 'dup@example.test']);
    $existing->delete();

    $this->actingAs($this->admin)
        ->postJson('/api/users', ['name' => 'X', 'email' => 'dup@example.test', 'locale' => 'ar', 'roles' => [Access::TEACHER]])
        ->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('rejects emails with header-injection characters', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/users', ['name' => 'X', 'email' => "x@example.test\r\nBcc: a@b.c", 'locale' => 'ar', 'roles' => [Access::TEACHER]])
        ->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('updates a user and audits role changes with old and new values', function () {
    $user = userWithRole(Access::TEACHER, ['email' => 't@example.test']);

    $this->actingAs($this->admin)
        ->putJson("/api/users/{$user->ulid}", ['name' => 'Renamed', 'email' => 't@example.test', 'locale' => 'en', 'roles' => [Access::TEACHER, Access::PARENT]])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed');

    $entry = Activity::where('event', 'roles_updated')->where('subject_id', $user->id)->latest('id')->first();
    expect($entry->properties['old']['roles'])->toBe([Access::TEACHER])
        ->and($entry->properties['attributes']['roles'])->toBe([Access::PARENT, Access::TEACHER]);
});

it('unbinds the Google identity and ends sessions when the email changes', function () {
    $user = userWithRole(Access::TEACHER, ['email' => 'old@example.test']);
    $user->forceFill(['google_sub' => 'sub-1'])->save();
    DB::table('sessions')->insert(['id' => 's1', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

    $this->actingAs($this->admin)
        ->putJson("/api/users/{$user->ulid}", ['name' => $user->name, 'email' => 'new@example.test', 'locale' => 'ar', 'roles' => [Access::TEACHER]])
        ->assertOk();

    expect($user->fresh()->google_sub)->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
});

it('lets a user change their own locale', function () {
    $teacher = userWithRole(Access::TEACHER, ['locale' => 'ar']);

    $this->actingAs($teacher)->patchJson('/api/me/locale', ['locale' => 'en'])->assertOk()->assertJsonPath('data.locale', 'en');
    $this->actingAs($teacher)->patchJson('/api/me/locale', ['locale' => 'fr'])->assertUnprocessable();
});

it('returns validation messages in the user locale', function () {
    $this->admin->update(['locale' => 'ar']);

    $this->actingAs($this->admin)->postJson('/api/users', [])
        ->assertUnprocessable()
        ->assertJsonPath('errors.name.0', 'حقل الاسم مطلوب.');
});

it('shows the audit log with filters', function () {
    $teacher = userWithRole(Access::TEACHER);
    $this->actingAs($this->admin)->postJson("/api/users/{$teacher->ulid}/deactivate")->assertOk();

    $this->actingAs($this->admin)->getJson('/api/audit-logs?log_name=users&event=updated')
        ->assertOk()
        ->assertJsonPath('data.0.causer.id', $this->admin->ulid)
        ->assertJsonPath('data.0.subject.id', $teacher->ulid)
        ->assertJsonPath('data.0.old.is_active', true)
        ->assertJsonPath('data.0.new.is_active', false);
});
