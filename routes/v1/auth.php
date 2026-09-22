<?php

use App\Http\Controllers\V1\User\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login/staff', [AuthController::class, 'loginStaff'])
        ->middleware('throttle:5,1');

    Route::post('login/student/password', [AuthController::class, 'loginStudentPassword'])
        ->middleware('throttle:5,1');

    Route::middleware('jwt')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
    });
});