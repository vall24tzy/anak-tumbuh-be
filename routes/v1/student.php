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
        });
    });

// IMPORT-001 — belum dirework, route lama dipertahankan apa adanya (di luar
// scope STUDENT-001). StudentImportService akan perlu disesuaikan ulang ke
// StudentService::store() yang baru (parameter rombel_id, bukan school_id/
// classroom/grade_level) saat IMPORT-001 dikerjakan.
Route::prefix('students/import')
    ->middleware(['jwt', 'role:1,2,3'])
    ->group(function () {
        Route::get('template', [StudentImportController::class, 'template']);
        Route::post('preview', [StudentImportController::class, 'preview']);
        Route::post('/', [StudentImportController::class, 'store']);
        Route::get('status/{batchId}', [StudentImportController::class, 'status']);
    });
