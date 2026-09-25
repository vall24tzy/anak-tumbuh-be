<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\School\HeadmasterController;

Route::prefix('headmasters')
    ->middleware(['jwt', 'role:super_admin'])
    ->group(function () {
        Route::get('/', [HeadmasterController::class, 'index']);
        Route::post('/', [HeadmasterController::class, 'store']);
        Route::get('/{id}', [HeadmasterController::class, 'show']);
        Route::post('/{id}/reset-password', [HeadmasterController::class, 'resetPassword']);
    });