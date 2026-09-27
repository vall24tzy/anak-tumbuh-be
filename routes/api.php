<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->get('/test', fn () => 'ok');

Route::prefix('v1')->group(function () {
    require base_path('routes/v1/auth.php');
    require base_path('routes/v1/school.php');
    require base_path('routes/v1/headmaster.php');
    require base_path('routes/v1/teacher.php');
    require base_path('routes/v1/academic-year.php');
    require base_path('routes/v1/rombel.php');
    require base_path('routes/v1/teacher-assignment.php');
    require base_path('routes/v1/teacher-import.php');
    require base_path('routes/v1/student.php');
    require base_path('routes/v1/student-class-assignment.php');
    require base_path('routes/v1/student-lifecycle.php');
    require base_path('routes/v1/habit.php');
    require base_path('routes/v1/bermasyarakat.php');
    require base_path('routes/v1/qr-token.php');
    require base_path('routes/v1/dashboard.php');
});