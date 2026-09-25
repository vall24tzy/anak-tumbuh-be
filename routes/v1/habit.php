<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\Habit\HabitController;
use App\Http\Controllers\V1\Student\StudentHabitController;

// API Contract v1.2 §21: GET /api/v1/habits adalah SATU-SATUNYA endpoint
// read-only untuk resource Habit/Indicator (Habit Master FIXED, tanpa CRUD
// untuk role manapun). Tidak ada endpoint /indicators standalone maupun
// GET /habits/{id}.
Route::prefix('habits')
    ->middleware(['jwt'])
    ->group(function () {
        Route::get('/', [HabitController::class, 'index']);
    });

// GET /habits/entries (+/{id}) — monitor/view: Teacher own active rombel,
// Student own (Matrix #33/#35). Headmaster/SA -> 403. Hanya 6 habit (Bermasyarakat
// dipisah, response shape beda). Didaftarkan sebelum POST {habit_id}/entries.
Route::prefix('habits/entries')
    ->middleware(['jwt', 'role:teacher,student'])
    ->group(function () {
        Route::get('/', [StudentHabitController::class, 'index']);
        Route::get('/{id}', [StudentHabitController::class, 'show'])->whereNumber('id');
    });

// POST /habits/{habit_id}/entries — Student only (Matrix #32). Berlaku 6
// habit SELAIN Bermasyarakat (habit_id Bermasyarakat -> 422 wrong_habit_endpoint,
// lihat StudentHabitService). Tidak ada PATCH/DELETE (Matrix #34).
Route::prefix('habits/{habit_id}/entries')
    ->middleware(['jwt', 'role:student'])
    ->group(function () {
        Route::post('/', [StudentHabitController::class, 'store'])->whereNumber('habit_id');
    });
