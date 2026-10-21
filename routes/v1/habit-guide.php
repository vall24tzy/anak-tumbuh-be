<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\Habit\HabitGuideController;

// INTEG-PANDUAN-001 — panduan kebiasaan per (rombel x habit).
// GET   /rombels/{rombel_id}/habit-guides            Teacher own active rombel, Student rombel sendiri (Matrix #64)
// PATCH /rombels/{rombel_id}/habit-guides/{habit_id}  Teacher own active rombel (Matrix #63)
// Super Admin/Headmaster -> 403 (middleware role). Tidak bentrok dengan routes/v1/rombel.php
// (pola path berbeda: {id} tunggal vs {rombel_id}/habit-guides).
Route::prefix('rombels/{rombel_id}/habit-guides')
    ->middleware('jwt')
    ->whereNumber('rombel_id')
    ->group(function () {
        Route::get('/', [HabitGuideController::class, 'index'])->middleware('role:teacher,student');
        Route::patch('/{habit_id}', [HabitGuideController::class, 'update'])
            ->middleware('role:teacher')
            ->whereNumber('habit_id');
    });
