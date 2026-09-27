<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\Dashboard\DashboardController;

Route::prefix('dashboard')
    ->middleware('jwt')
    ->group(function () {
        Route::middleware('role:student')->get('student', [DashboardController::class, 'student']);
        Route::middleware('role:teacher')->get('teacher', [DashboardController::class, 'teacher']);
        Route::middleware('role:headmaster')->get('headmaster', [DashboardController::class, 'headmaster']);
        Route::middleware('role:super_admin')->get('super-admin', [DashboardController::class, 'superAdmin']);
    });