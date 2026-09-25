<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\Student\BermasyarakatController;

// API Contract v1.2 §22. POST: Student only (Matrix #36). GET list: Teacher
// (own active rombel) & Student (own) — Matrix #33; Headmaster/SA -> 403.
Route::prefix('bermasyarakat/entries')
    ->middleware('jwt')
    ->group(function () {
        Route::middleware('role:teacher,student')->get('/', [BermasyarakatController::class, 'index']);
        Route::middleware('role:student')->post('/', [BermasyarakatController::class, 'store']);
    });
