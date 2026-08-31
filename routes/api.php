<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->get('/test', fn () => 'ok');