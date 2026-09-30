<?php

use App\Http\Controllers\V1\Gamification\GamificationController;
use App\Http\Controllers\V1\Gamification\RankingController;
use Illuminate\Support\Facades\Route;

// LEVEL-RANKING-001 — API Contract v1.2 §23/§24, Matrix #38/#39. Semua GET
// (read-only); tidak ada POST/PATCH/DELETE untuk Level/poin/threshold.
Route::middleware('jwt')->group(function () {
    // Student OWN. Perilaku non-Student belum dirinci SoT -> NOTE-11.
    Route::get('gamification/me', [GamificationController::class, 'me'])
        ->middleware('role:student');

    // SA global, HM own school, Teacher own active rombel (scope di Service, 404 bila di luar scope).
    Route::get('students/{id}/gamification', [GamificationController::class, 'forStudent'])
        ->middleware('role:super_admin,headmaster,teacher');

    // Teacher & Student saja (HM/SA tidak punya ranking khusus).
    Route::prefix('rankings')
        ->middleware('role:teacher,student')
        ->group(function () {
            Route::get('rombel', [RankingController::class, 'rombel']);
            Route::get('angkatan', [RankingController::class, 'angkatan']);
        });
});
