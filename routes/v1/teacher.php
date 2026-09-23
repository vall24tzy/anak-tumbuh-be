<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\School\TeacherController;

Route::prefix('teachers')
    ->middleware(['jwt', 'role:headmaster'])
    ->group(function () {
        Route::get('/', [TeacherController::class, 'index']);
        Route::post('/', [TeacherController::class, 'store']);
        Route::get('/{id}', [TeacherController::class, 'show']);
        Route::post('/{id}/inactive', [TeacherController::class, 'inactive']);
    });