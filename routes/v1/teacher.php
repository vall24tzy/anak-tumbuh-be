<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\School\TeacherController;

Route::prefix('teachers')
    ->middleware(['jwt', 'role:headmaster'])
    ->group(function () {
        Route::get('/', [TeacherController::class, 'index']);
        Route::post('/', [TeacherController::class, 'store']);
        Route::get('/{id}', [TeacherController::class, 'show']);
        // INTEG-TEACHER-001 (Matrix #66): ubah name + gender guru, Headmaster own school (grup ini role:headmaster).
        Route::patch('/{id}', [TeacherController::class, 'update']);
        Route::post('/{id}/inactive', [TeacherController::class, 'inactive']);
        Route::post('/{id}/reset-password', [TeacherController::class, 'resetPassword'])
            ->middleware('throttle.password');
    });