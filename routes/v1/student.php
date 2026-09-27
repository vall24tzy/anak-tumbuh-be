<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\Student\StudentController;
use App\Http\Controllers\V1\Student\StudentImportController;

// API Contract v1.2 §17. TIDAK ADA PUT/DELETE — Matrix #12 (hard delete DENY
// semua role). Create/Update Teacher only (Matrix #7/#10); SA/HM tidak create/
// update Student.
Route::prefix('students')
    ->middleware('jwt')
    ->group(function () {
        Route::get('/', [StudentController::class, 'index']);
        Route::get('/{id}', [StudentController::class, 'show']);

        Route::middleware('role:teacher')->group(function () {
            Route::post('/', [StudentController::class, 'store']);
            Route::patch('/{id}', [StudentController::class, 'update']);
            Route::post('/{id}/reset-password', [StudentController::class, 'resetPassword']);
        });
    });

// IMPORT-001 — API Contract v1.2 §18/§28. Teacher only, own active rombel.
// Path top-level "student-imports" (BUKAN nested di bawah "students") sesuai
// kontrak — menggantikan path lama "students/import/*" (role:1,2,3, SA/HM
// diizinkan) yang sudah tidak sesuai baseline final.
Route::prefix('student-imports')
    ->middleware(['jwt', 'role:teacher'])
    ->group(function () {
        Route::get('template', [StudentImportController::class, 'template']);
        Route::post('preview', [StudentImportController::class, 'preview']);
        Route::post('commit', [StudentImportController::class, 'commit']);
        // NOTE-09 — endpoint status/polling queue, tidak ada di API Contract
        // v1.2, dipertahankan sebagai technical necessity (lihat instruksi
        // task poin 6 & docs/db-gap-analysis.md).
        Route::get('status/{batchId}', [StudentImportController::class, 'status']);
    });
