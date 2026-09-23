<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\School\TeacherAssignmentController;

Route::prefix('teacher-class-assignments')
    ->middleware(['jwt', 'role:headmaster,teacher'])
    ->group(function () {
        Route::get('/', [TeacherAssignmentController::class, 'index']);
        Route::get('/{id}', [TeacherAssignmentController::class, 'show']);
    });

Route::prefix('teacher-class-assignments')
    ->middleware(['jwt', 'role:headmaster'])
    ->group(function () {
        Route::post('/', [TeacherAssignmentController::class, 'store']);
        Route::patch('/{id}', [TeacherAssignmentController::class, 'update']);
        Route::post('/{id}/deactivate', [TeacherAssignmentController::class, 'deactivate']);
    });