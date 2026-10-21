<?php

use Illuminate\Support\Facades\Route;

// Stage 0-B residual F-14: sebelumnya `auth:api` (guard JWT polos) - hanya cek token valid,
// tidak cek status lifecycle maupun sidik jari password. Sekarang lewat alias `jwt`
// (AuthenticateActiveSession) seperti semua route terproteksi lain.
Route::middleware('jwt')->get('/test', fn () => 'ok');

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
    require base_path('routes/v1/habit-guide.php');
    require base_path('routes/v1/bermasyarakat.php');
    require base_path('routes/v1/qr-token.php');
    require base_path('routes/v1/dashboard.php');
    require base_path('routes/v1/gamification.php');
});