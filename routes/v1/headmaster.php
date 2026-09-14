<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\School\HeadmasterController;

Route::prefix('headmaster')
    ->middleware(['jwt', 'role:1'])
    ->group(function () {
        Route::get('/', [HeadmasterController::class, 'index']);
        Route::post('/', [HeadmasterController::class, 'store']);
        Route::get('/{id}', [HeadmasterController::class, 'show']);
        Route::put('/{id}', [HeadmasterController::class, 'update']);
        Route::delete('/{id}', [HeadmasterController::class, 'destroy']);
    });