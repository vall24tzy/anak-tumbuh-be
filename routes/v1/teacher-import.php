<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\School\TeacherImportController;

Route::prefix('teachers/import')
    ->middleware(['jwt', 'role:headmaster'])
    ->group(function () {
        Route::get('template', [TeacherImportController::class, 'template']);
        Route::post('/', [TeacherImportController::class, 'import']);
        Route::get('status/{batchId}', [TeacherImportController::class, 'status']);
    });