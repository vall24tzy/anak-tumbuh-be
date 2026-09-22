<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\Habit\HabitController;

// API Contract v1.2 §21: GET /api/v1/habits adalah SATU-SATUNYA endpoint
// untuk resource Habit/Indicator — semua role terautentikasi (read-only).
// Tidak ada POST/PUT/PATCH/DELETE (Habit Master FIXED, tanpa CRUD untuk
// role manapun termasuk Super Admin), dan tidak ada endpoint /indicators
// standalone maupun GET /habits/{id}.
Route::prefix('habits')
    ->middleware(['jwt'])
    ->group(function () {
        Route::get('/', [HabitController::class, 'index']);
    });
