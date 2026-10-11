<?php

namespace App\Services;

use App\Models\User;
use App\Support\UserRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Two-step CSV import: preview (parse + validate every row, nothing saved),
 * then commit (re-validate and save only the rows that are still valid).
 *
 * Expected header: name,email,roles,locale,phone  (locale and phone optional;
 * multiple roles separated by ";").
 */
class UserCsvImport
{
    private const REQUIRED_COLUMNS = ['name', 'email', 'roles'];

    private const KNOWN_COLUMNS = ['name', 'email', 'roles', 'locale', 'phone'];

    public function __construct(private readonly UserManager $users) {}

    /**
     * @return array{token: string, rows: list<array<string, mixed>>, summary: array{total: int, valid: int, invalid: int}}
     */
    public function preview(UploadedFile $file, User $actor): array
    {
        $rows = $this->validateRows($this->parse($file), $actor);
        $token = Str::random(40);

        Cache::put($this->cacheKey($actor, $token), $rows, now()->addMinutes(30));

        return ['token' => $token, 'rows' => $rows, 'summary' => $this->summarise($rows)];
    }

    /**
     * @return array{created: int, rows: list<array<string, mixed>>, summary: array{total: int, valid: int, invalid: int}}
     */
    public function commit(string $token, User $actor): array
    {
        $rows = Cache::pull($this->cacheKey($actor, $token));

        if (! is_array($rows)) {
            throw ValidationException::withMessages(['token' => __('import.expired')]);
        }

        // Re-validate: the database may have changed since the preview.
        $rows = $this->validateRows(array_map(fn (array $row) => ['line' => $row['line'], 'data' => $row['data']], $rows), $actor);

        $created = 0;
        foreach ($rows as $row) {
            if ($row['errors'] !== []) {
                continue;
            }

            $data = $row['data'];
            $this->users->create(
                ['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'], 'locale' => $data['locale']],
                $data['roles'],
                $actor,
            );
            $created++;
        }

        activity('users')
            ->event('users_imported')
            ->causedBy($actor)
            ->withProperties(['created' => $created, 'skipped' => count($rows) - $created])
            ->log('users_imported');

        return ['created' => $created, 'rows' => $rows, 'summary' => $this->summarise($rows)];
    }

    /**
     * @return list<array{line: int, data: array<string, mixed>}>
     */
    private function parse(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        try {
            $header = fgetcsv($handle, escape: '');

            if (! is_array($header)) {
                throw ValidationException::withMessages(['file' => __('import.empty')]);
            }

            $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header);
            $missing = array_diff(self::REQUIRED_COLUMNS, $header);

            if ($missing !== []) {
                throw ValidationException::withMessages(['file' => __('import.missing_columns', ['columns' => implode(', ', $missing)])]);
            }

            $rows = [];
            $line = 1;
            $max = config('school.import.max_rows');

            while (($values = fgetcsv($handle, escape: '')) !== false) {
                $line++;

                if ($values === [null] || implode('', array_map('trim', array_map('strval', $values))) === '') {
                    continue; // blank line
                }

                if (count($rows) >= $max) {
                    throw ValidationException::withMessages(['file' => __('import.too_many_rows', ['max' => $max])]);
                }

                $record = [];
                foreach (self::KNOWN_COLUMNS as $column) {
                    $index = array_search($column, $header, true);
                    $record[$column] = $index === false ? null : trim((string) ($values[$index] ?? ''));
                }

                $rows[] = ['line' => $line, 'data' => $this->normalise($record)];
            }

            if ($rows === []) {
                throw ValidationException::withMessages(['file' => __('import.empty')]);
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  array<string, ?string>  $record
     * @return array<string, mixed>
     */
    private function normalise(array $record): array
    {
        return [
            'name' => $record['name'] ?? '',
            'email' => strtolower($record['email'] ?? ''),
            'roles' => array_values(array_filter(array_map(
                fn ($r) => strtolower(trim($r)),
                preg_split('/[;|]/', $record['roles'] ?? '') ?: [],
            ))),
            'locale' => ($record['locale'] ?? '') !== '' ? strtolower($record['locale']) : 'ar',
            'phone' => ($record['phone'] ?? '') !== '' ? $record['phone'] : null,
        ];
    }

    /**
     * @param  list<array{line: int, data: array<string, mixed>}>  $rows
     * @return list<array{line: int, data: array<string, mixed>, errors: list<string>}>
     */
    private function validateRows(array $rows, User $actor): array
    {
        $rules = UserRules::for($actor);
        $emailCounts = array_count_values(array_map(fn ($row) => $row['data']['email'], $rows));

        return array_map(function (array $row) use ($rules, $emailCounts) {
            $errors = Validator::make($row['data'], $rules)->errors()->all();

            if ($row['data']['email'] !== '' && $emailCounts[$row['data']['email']] > 1) {
                $errors[] = __('import.duplicate_in_file');
            }

            return ['line' => $row['line'], 'data' => $row['data'], 'errors' => array_values(array_unique($errors))];
        }, $rows);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{total: int, valid: int, invalid: int}
     */
    private function summarise(array $rows): array
    {
        $invalid = count(array_filter($rows, fn ($row) => $row['errors'] !== []));

        return ['total' => count($rows), 'valid' => count($rows) - $invalid, 'invalid' => $invalid];
    }

    private function cacheKey(User $actor, string $token): string
    {
        // Bound to the uploader: one admin cannot commit another admin's preview.
        return 'user-import:'.$actor->getKey().':'.$token;
    }
}
