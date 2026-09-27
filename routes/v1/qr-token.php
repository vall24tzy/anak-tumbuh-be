<?php

use App\Http\Controllers\V1\Student\QrTokenController;
use Illuminate\Support\Facades\Route;

// API Contract v1.2 §10/§17 (menumpang scope reset-password Student).
// Ditaruh terpisah dari routes/v1/student.php karena path top-level
// /qr-tokens/{student_id}/... bukan /students/... — pola sama dengan
// routes/v1/student-lifecycle.php untuk /rombels/{id}/bulk-graduate.
Route::middleware(['jwt', 'role:teacher'])
    ->post('qr-tokens/{studentId}/regenerate', [QrTokenController::class, 'regenerate']);
