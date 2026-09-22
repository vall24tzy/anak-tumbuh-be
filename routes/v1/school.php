<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\School\SchoolController;

Route::prefix('schools')
    ->middleware(['jwt', 'role:super_admin'])
    ->group(function () {
        Route::get('/', [SchoolController::class, 'index']);
        Route::post('/', [SchoolController::class, 'store']);
        Route::get('/{id}', [SchoolController::class, 'show']);
    });