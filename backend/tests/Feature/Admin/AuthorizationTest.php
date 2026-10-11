<?php

use App\Models\User;
use App\Support\Access;
use Illuminate\Http\UploadedFile;

dataset('admin endpoints', [
    'list users' => ['GET', '/api/users'],
    'create user' => ['POST', '/api/users'],
    'show user' => ['GET', '/api/users/{target}'],
    'update user' => ['PUT', '/api/users/{target}'],
    'activate user' => ['POST', '/api/users/{target}/activate'],
    'deactivate user' => ['POST', '/api/users/{target}/deactivate'],
    'list roles' => ['GET', '/api/roles'],
    'import preview' => ['POST', '/api/users/import/preview'],
    'import commit' => ['POST', '/api/users/import/commit'],
    'import template' => ['GET', '/api/users/import/template'],
    'audit log' => ['GET', '/api/audit-logs'],
]);

it('gives a teacher 403 on every admin endpoint', function (string $method, string $uri) {
    $teacher = userWithRole(Access::TEACHER);
    $target = userWithRole(Access::STUDENT);

    $payload = [
        'name' => 'X', 'email' => 'x@example.test', 'locale' => 'ar', 'roles' => [Access::STUDENT],
        'token' => str_repeat('a', 40),
        'file' => UploadedFile::fake()->createWithContent('u.csv', "name,email,roles\nA,a@example.test,student\n"),
    ];

    $this->actingAs($teacher)
        ->json($method, str_replace('{target}', $target->ulid, $uri), $payload)
        ->assertForbidden();
})->with('admin endpoints');

it('gives the other non-admin roles 403 as well', function (string $role) {
    $this->actingAs(userWithRole($role))->getJson('/api/users')->assertForbidden();
    $this->actingAs(userWithRole($role))->getJson('/api/audit-logs')->assertForbidden();
})->with([Access::STUDENT, Access::PARENT, Access::HOD, Access::GUARD, Access::DRIVER, Access::HELPER]);

it('requires authentication', function (string $method, string $uri) {
    $target = userWithRole(Access::STUDENT);

    $this->json($method, str_replace('{target}', $target->ulid, $uri))->assertUnauthorized();
})->with('admin endpoints');

it('lets admins and IT reach admin endpoints', function (string $role) {
    $this->actingAs(userWithRole($role))->getJson('/api/users')->assertOk();
    $this->actingAs(userWithRole($role))->getJson('/api/audit-logs')->assertOk();
})->with([Access::ADMIN, Access::IT, Access::SUPER_ADMIN]);

it('returns every user permission in /api/me', function () {
    $this->actingAs(userWithRole(Access::IT))
        ->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('data.roles', [Access::IT])
        ->assertJsonPath('data.permissions', [Access::IMPORT_USERS, Access::MANAGE_ROLES, Access::MANAGE_USERS, Access::VIEW_AUDIT_LOG]);
});

it('uses the ulid in URLs, never the numeric id', function () {
    $admin = userWithRole(Access::ADMIN);
    $target = userWithRole(Access::TEACHER);

    $this->actingAs($admin)->getJson("/api/users/{$target->id}")->assertNotFound();
    $this->actingAs($admin)->getJson("/api/users/{$target->ulid}")
        ->assertOk()
        ->assertJsonPath('data.id', $target->ulid)
        ->assertJsonMissingPath('data.ulid');
});

it('does not let an admin (without manage-roles) assign privileged roles', function (string $role) {
    $this->actingAs(userWithRole(Access::ADMIN))
        ->postJson('/api/users', ['name' => 'X', 'email' => 'x@example.test', 'locale' => 'ar', 'roles' => [$role]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('roles.0');
})->with([Access::SUPER_ADMIN, Access::IT, Access::ADMIN, Access::HOD]);

it('does not let IT assign the super-admin role', function () {
    $this->actingAs(userWithRole(Access::IT))
        ->postJson('/api/users', ['name' => 'X', 'email' => 'x@example.test', 'locale' => 'ar', 'roles' => [Access::SUPER_ADMIN]])
        ->assertUnprocessable();
});

it('lets IT assign the admin role', function () {
    $this->actingAs(userWithRole(Access::IT))
        ->postJson('/api/users', ['name' => 'X', 'email' => 'x@example.test', 'locale' => 'ar', 'roles' => [Access::ADMIN]])
        ->assertCreated();
});

it('does not let an admin edit or deactivate a super-admin or IT user', function (string $role) {
    $admin = userWithRole(Access::ADMIN);
    $target = userWithRole($role);

    $this->actingAs($admin)
        ->putJson("/api/users/{$target->ulid}", ['name' => 'X', 'email' => $target->email, 'locale' => 'ar', 'roles' => [Access::TEACHER]])
        ->assertForbidden();
    $this->actingAs($admin)->postJson("/api/users/{$target->ulid}/deactivate")->assertForbidden();
})->with([Access::SUPER_ADMIN, Access::IT]);

it('rejects mass-assignment of protected fields', function () {
    $this->actingAs(userWithRole(Access::ADMIN))
        ->postJson('/api/users', [
            'name' => 'X', 'email' => 'x@example.test', 'locale' => 'ar', 'roles' => [Access::TEACHER],
            'is_active' => false, 'google_sub' => 'hijack', 'password' => 'x',
        ])
        ->assertCreated();

    $user = User::where('email', 'x@example.test')->first();
    expect($user->is_active)->toBeTrue()
        ->and($user->google_sub)->toBeNull()
        ->and($user->password)->toBeNull();
});
