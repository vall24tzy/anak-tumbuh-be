<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\Student\StudentController;
use App\Http\Controllers\V1\Student\StudentImportController;

Route::prefix('students')
    ->middleware(['jwt', 'role:1,2,3'])
    ->group(function () {
        Route::get('/', [StudentController::class, 'index']);
        Route::post('/', [StudentController::class, 'store']);
        Route::get('/{id}', [StudentController::class, 'show']);
        Route::put('/{id}', [StudentController::class, 'update']);
        Route::delete('/{id}', [StudentController::class, 'destroy']);
    });

Route::prefix('students/import')
    ->middleware(['jwt', 'role:1,2,3'])
    ->group(function () {
        Route::get('template', [StudentImportController::class, 'template']);
        Route::post('preview', [StudentImportController::class, 'preview']);
        Route::post('/', [StudentImportController::class, 'store']);
        Route::get('status/{batchId}', [StudentImportController::class, 'status']);
    });