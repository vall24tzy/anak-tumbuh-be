<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\School\SchoolController;

Route::prefix('schools')
    ->middleware(['jwt', 'role:1,2'])
    ->group(function () {
        Route::get('/', [SchoolController::class, 'index']);
        Route::post('/', [SchoolController::class, 'store']);
        Route::get('/{id}', [SchoolController::class, 'show']);
        Route::put('/{id}', [SchoolController::class, 'update']);
        Route::delete('/{id}', [SchoolController::class, 'destroy']);
    });