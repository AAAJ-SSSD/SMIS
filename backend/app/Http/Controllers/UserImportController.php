<?php

namespace App\Http\Controllers;

use App\Http\Requests\Users\ImportCommitRequest;
use App\Http\Requests\Users\ImportPreviewRequest;
use App\Models\User;
use App\Services\UserCsvImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class UserImportController extends Controller
{
    public function __construct(private readonly UserCsvImport $import) {}

    public function preview(ImportPreviewRequest $request): JsonResponse
    {
        return response()->json($this->import->preview($request->file('file'), $request->user()));
    }

    public function commit(ImportCommitRequest $request): JsonResponse
    {
        return response()->json($this->import->commit($request->validated('token'), $request->user()));
    }

    /**
     * A CSV template with the expected header.
     */
    public function template(): Response
    {
        Gate::authorize('import', User::class);

        return response("name,email,roles,locale,phone\n", 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="users-template.csv"',
        ]);
    }
}
