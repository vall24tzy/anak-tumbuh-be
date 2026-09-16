<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\Habit\HabitController;
use App\Http\Controllers\V1\Habit\IndicatorController;

// Baca (index/show): semua role yang login boleh akses — dipakai role lain (Guru/Siswa)
// untuk menampilkan daftar Habit & Indicator saat mencatat kebiasaan (STUDENTHABIT-001).
// Tulis (store/update/destroy): HANYA Super Admin (role:1) — poin ditetapkan terpusat,
// sekolah tidak boleh mengubah struktur poin (Requirement v2.1 §5).
Route::prefix('habits')
    ->middleware(['jwt'])
    ->group(function () {
        Route::get('/', [HabitController::class, 'index']);
        Route::get('/{id}', [HabitController::class, 'show']);

        Route::middleware(['role:1'])->group(function () {
            Route::post('/', [HabitController::class, 'store']);
            Route::put('/{id}', [HabitController::class, 'update']);
            Route::delete('/{id}', [HabitController::class, 'destroy']);
        });
    });

Route::prefix('indicators')
    ->middleware(['jwt'])
    ->group(function () {
        Route::get('/', [IndicatorController::class, 'index']);
        Route::get('/{id}', [IndicatorController::class, 'show']);

        Route::middleware(['role:1'])->group(function () {
            Route::post('/', [IndicatorController::class, 'store']);
            Route::put('/{id}', [IndicatorController::class, 'update']);
            Route::delete('/{id}', [IndicatorController::class, 'destroy']);
        });
    });