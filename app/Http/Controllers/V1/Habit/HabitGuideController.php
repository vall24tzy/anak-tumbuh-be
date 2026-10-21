<?php

namespace App\Http\Controllers\V1\Habit;

use App\Http\Controllers\Controller;
use App\Http\Requests\Habit\UpdateHabitGuideRequest;
use App\Services\Habit\HabitGuideService;
use App\Traits\ApiResponseTraits;

// INTEG-PANDUAN-001 — panduan kebiasaan per (rombel x habit).
// GET: Teacher + Student (Matrix #64). PATCH: Teacher saja (Matrix #63). Role lain 403 via
// middleware (lihat routes/v1/habit-guide.php).

class HabitGuideController extends Controller
{
    use ApiResponseTraits;

    public function __construct(protected HabitGuideService $service) {}

    public function index(int $rombel_id)
    {
        return $this->successResponse($this->service->list($rombel_id));
    }

    public function update(UpdateHabitGuideRequest $request, int $rombel_id, int $habit_id)
    {
        return $this->successResponse(
            $this->service->write($rombel_id, $habit_id, $request->validated()['content'] ?? null)
        );
    }
}
