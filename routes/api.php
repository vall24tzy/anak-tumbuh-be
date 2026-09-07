<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->get('/test', fn () => 'ok');

Route::prefix('v1')->group(function () {
    require base_path('routes/v1/auth.php');
    require base_path('routes/v1/school.php');
});