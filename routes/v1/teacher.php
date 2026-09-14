<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\School\TeacherController;

Route::prefix('teachers')
    ->middleware(['jwt', 'role:1,2'])
    ->group(function () {
        Route::get('/', [TeacherController::class, 'index']);
        Route::post('/', [TeacherController::class, 'store']);
        Route::get('/{id}', [TeacherController::class, 'show']);
        Route::put('/{id}', [TeacherController::class, 'update']);
        Route::delete('/{id}', [TeacherController::class, 'destroy']);
    });