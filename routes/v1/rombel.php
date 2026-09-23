<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\School\RombelController;

Route::prefix('rombels')
    ->middleware('jwt')
    ->group(function () {
        // Headmaster & Teacher, GET-only untuk Teacher (VIEW ONLY)
        Route::middleware('role:headmaster,teacher')->group(function () {
            Route::get('/', [RombelController::class, 'index']);
            Route::get('/{id}', [RombelController::class, 'show']);
        });

        // Headmaster only — write actions
        Route::middleware('role:headmaster')->group(function () {
            Route::post('/', [RombelController::class, 'store']);
            Route::patch('/{id}', [RombelController::class, 'update']);
            Route::delete('/{id}', [RombelController::class, 'destroy']);
        });
    });