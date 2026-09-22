<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\School\AcademicYearController;

Route::prefix('academic-years')
    ->middleware('jwt')
    ->group(function () {
        // Headmaster & Teacher, GET-only untuk Teacher (VIEW ONLY)
        Route::middleware('role:headmaster,teacher')->group(function () {
            Route::get('/', [AcademicYearController::class, 'index']);
            Route::get('/{id}', [AcademicYearController::class, 'show']);
        });

        // Headmaster only — write actions
        Route::middleware('role:headmaster')->group(function () {
            Route::post('/', [AcademicYearController::class, 'store']);
            Route::patch('/{id}', [AcademicYearController::class, 'update']);
            Route::post('/{id}/activate', [AcademicYearController::class, 'activate']);
        });
    });