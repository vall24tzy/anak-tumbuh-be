<?php

use App\Http\Controllers\V1\User\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('signin', [AuthController::class, 'signin']);
    // Butuh token valid (bukan buat login, tapi buat logout/refresh token yang lagi aktif)
    Route::middleware('jwt')->group(function () {
        Route::delete('signout', [AuthController::class, 'signout']);
        Route::get('refresh', [AuthController::class, 'refresh']);
    });
});