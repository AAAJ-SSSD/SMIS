<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserImportController;
use Illuminate\Support\Facades\Route;

/*
| Every route here requires an authenticated, active user. Authorisation for
| each action is enforced by FormRequest::authorize() or Gate::authorize()
| against a Policy; the SPA menu is never relied on.
*/

Route::middleware(['auth:sanctum', 'active', 'throttle:api'])->group(function () {
    Route::get('me', [ProfileController::class, 'show'])->name('me.show');
    Route::patch('me/locale', [ProfileController::class, 'updateLocale'])->name('me.locale');

    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');

    Route::get('users/import/template', [UserImportController::class, 'template'])->name('users.import.template');
    Route::post('users/import/preview', [UserImportController::class, 'preview'])->name('users.import.preview');
    Route::post('users/import/commit', [UserImportController::class, 'commit'])->name('users.import.commit');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::post('users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
    Route::post('users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');

    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
});
