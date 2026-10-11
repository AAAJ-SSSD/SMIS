<?php

use App\Models\User;
use App\Support\Access;
use Illuminate\Http\UploadedFile;

function csv(string $content): UploadedFile
{
    return UploadedFile::fake()->createWithContent('users.csv', $content);
}

beforeEach(fn () => $this->admin = userWithRole(Access::ADMIN));

it('previews rows with a per-row error report and saves nothing', function () {
    userWithRole(Access::TEACHER, ['email' => 'taken@example.test']);

    $file = csv("\xEF\xBB\xBFName,Email,Roles,Locale,Phone\n"
        ."Good One,good@example.test,teacher;parent,en,\n"
        ."Taken,taken@example.test,teacher,,\n"
        .",noname@example.test,student,,\n"
        ."Bad Role,badrole@example.test,admin,,\n"
        ."Bad Email,not-an-email,student,,\n"
        ."Dup A,dup@example.test,student,,\n"
        ."Dup B,dup@example.test,student,,\n");

    $response = $this->actingAs($this->admin)
        ->postJson('/api/users/import/preview', ['file' => $file])
        ->assertOk()
        ->assertJsonPath('summary', ['total' => 7, 'valid' => 1, 'invalid' => 6]);

    $rows = collect($response->json('rows'))->keyBy('line');
    expect($rows[2]['errors'])->toBe([])
        ->and($rows[2]['data']['roles'])->toBe(['teacher', 'parent'])
        ->and($rows[3]['errors'])->not->toBeEmpty()
        ->and($rows[4]['errors'])->not->toBeEmpty()
        ->and($rows[5]['errors'])->not->toBeEmpty() // admin role not assignable by an admin
        ->and($rows[6]['errors'])->not->toBeEmpty()
        ->and($rows[7]['errors'])->not->toBeEmpty()
        ->and($rows[8]['errors'])->not->toBeEmpty();

    expect(User::where('email', 'good@example.test')->exists())->toBeFalse();
});

it('commits only the valid rows after preview', function () {
    $token = $this->actingAs($this->admin)
        ->postJson('/api/users/import/preview', ['file' => csv("name,email,roles\nA,a@example.test,teacher\nB,bad,student\n")])
        ->json('token');

    $this->actingAs($this->admin)
        ->postJson('/api/users/import/commit', ['token' => $token])
        ->assertOk()
        ->assertJsonPath('created', 1);

    expect(User::where('email', 'a@example.test')->first()->getRoleNames()->all())->toBe(['teacher'])
        ->and(User::where('email', 'bad')->exists())->toBeFalse();
});

it('re-validates at commit time', function () {
    $token = $this->actingAs($this->admin)
        ->postJson('/api/users/import/preview', ['file' => csv("name,email,roles\nA,a@example.test,teacher\n")])
        ->json('token');

    userWithRole(Access::TEACHER, ['email' => 'a@example.test']); // created in the meantime

    $this->actingAs($this->admin)
        ->postJson('/api/users/import/commit', ['token' => $token])
        ->assertOk()
        ->assertJsonPath('created', 0);
});

it('does not let another admin commit someone else\'s preview', function () {
    $token = $this->actingAs($this->admin)
        ->postJson('/api/users/import/preview', ['file' => csv("name,email,roles\nA,a@example.test,teacher\n")])
        ->json('token');

    $this->actingAs(userWithRole(Access::ADMIN))
        ->postJson('/api/users/import/commit', ['token' => $token])
        ->assertUnprocessable();

    expect(User::where('email', 'a@example.test')->exists())->toBeFalse();
});

it('rejects a file without the required columns', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/users/import/preview', ['file' => csv("full_name,mail\nA,a@example.test\n")])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');
});

it('enforces the school domain on imported rows', function () {
    enableGoogleLogin();

    $this->actingAs($this->admin)
        ->postJson('/api/users/import/preview', ['file' => csv("name,email,roles\nA,a@gmail.com,teacher\nB,b@".SCHOOL_DOMAIN.",teacher\n")])
        ->assertOk()
        ->assertJsonPath('summary.valid', 1);
});
