<?php

use App\Http\Controllers\V1\User\AuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->get('/test', fn () => 'ok');

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('signin', [AuthController::class, 'signin']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);

        // Butuh token valid (bukan buat login, tapi buat logout/refresh token yang lagi aktif)
        Route::middleware('jwt')->group(function () {
            Route::delete('signout', [AuthController::class, 'signout']);
            Route::get('refresh', [AuthController::class, 'refresh']);
        });
    });
});
