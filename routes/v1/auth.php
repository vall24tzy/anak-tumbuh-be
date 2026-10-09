<?php

use App\Http\Controllers\V1\User\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    // F-23: dua counter independen per route login — `throttle:5,1` (per IP) dan
    // `throttle:login-identifier` (per username/nisn; lihat AppServiceProvider).
    Route::post('login/staff', [AuthController::class, 'loginStaff'])
        ->middleware(['throttle:5,1', 'throttle:login-identifier']);

    Route::post('login/student/password', [AuthController::class, 'loginStudentPassword'])
        ->middleware(['throttle:5,1', 'throttle:login-identifier']);

    Route::post('login/student/qr', [AuthController::class, 'loginStudentQr'])
        ->middleware(['throttle:5,1', 'throttle:login-identifier']);

    Route::middleware('jwt')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);

        Route::middleware('role:super_admin')->group(function () {
            Route::post('change-password', [AuthController::class, 'changePassword'])
                ->middleware('throttle.password');   
        });
    });
});