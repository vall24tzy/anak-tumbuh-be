<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\Student\StudentClassAssignmentController;

// API Contract v1.2 §19. Route PATCH mengikuti Keputusan Tim NOTE-05
// (docs/db-gap-analysis.md) — body cuma { "status": "ended" }.

Route::prefix('student-class-assignments')
    ->middleware('jwt')
    ->group(function () {
        // SA/HM/Teacher/Student — scope difilter di Service (Matrix #25)
        Route::get('/', [StudentClassAssignmentController::class, 'index']);

        // Teacher only — own active rombel
        Route::middleware('role:teacher')->group(function () {
            Route::post('/', [StudentClassAssignmentController::class, 'store']);
            Route::patch('/{id}', [StudentClassAssignmentController::class, 'update']);
        });
    });

// History lengkap per Student — SA/HM/Teacher/Student, scope difilter di Service
Route::get('students/{id}/class-assignments', [StudentClassAssignmentController::class, 'historyForStudent'])
    ->middleware('jwt');
