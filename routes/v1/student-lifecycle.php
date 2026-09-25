<?php

use App\Http\Controllers\V1\Student\StudentLifecycleController;
use Illuminate\Support\Facades\Route;

// STUDENT-LIFECYCLE-001 — API Contract v1.2 §20, Matrix #41-43.
// Teacher only, own active rombel. Ditaruh terpisah dari routes/v1/student.php
// dan routes/v1/rombel.php karena bulk-graduate memakai path /rombels/{id}/...
// meski domainnya Student (bukan Master Rombel milik Headmaster) — supaya
// tidak tercampur dengan RombelController (CRUD Headmaster).
Route::middleware(['jwt', 'role:teacher'])->group(function () {
    Route::post('students/{id}/inactive', [StudentLifecycleController::class, 'inactive']);
    Route::post('students/{id}/graduate', [StudentLifecycleController::class, 'graduate']);
    Route::post('rombels/{id}/bulk-graduate', [StudentLifecycleController::class, 'bulkGraduate']);
});
