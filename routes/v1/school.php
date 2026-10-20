<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\School\SchoolController;

Route::prefix('schools')
    ->middleware(['jwt', 'role:super_admin'])
    ->group(function () {
        Route::get('/', [SchoolController::class, 'index']);
        Route::post('/', [SchoolController::class, 'store']);
        Route::get('/{id}', [SchoolController::class, 'show']);
        // INTEG-SCHOOL-001 (D-09, Matrix #65): ubah nama sekolah, Super Admin saja (grup ini role:super_admin).
        Route::patch('/{id}', [SchoolController::class, 'update']);
    });