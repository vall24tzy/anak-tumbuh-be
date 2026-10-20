<?php

namespace App\Http\Controllers\V1\Habit;

use App\Http\Controllers\Controller;
use App\Http\Requests\Habit\HabitRecapRequest;
use App\Services\Habit\HabitRecapService;
use App\Traits\ApiResponseTraits;

// INTEG-REKAP-001 — GET /habits/recap. Teacher only (Matrix #62), read-only.
// Role lain -> 403 via middleware role:teacher (lihat routes/v1/habit.php).

class HabitRecapController extends Controller
{
    use ApiResponseTraits;

    public function __construct(protected HabitRecapService $service) {}

    public function index(HabitRecapRequest $request)
    {
        return $this->successResponse($this->service->recap($request->validated()));
    }
}
