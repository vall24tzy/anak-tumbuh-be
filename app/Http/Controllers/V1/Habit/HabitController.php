<?php

namespace App\Http\Controllers\V1\Habit;

use App\Http\Controllers\Controller;
use App\Http\Resources\Habit\HabitResource;
use App\Services\Habit\HabitService;
use App\Traits\ApiResponseTraits;

// API Contract v1.2 §21: Habit Master FIXED, GET /api/v1/habits adalah
// SATU-SATUNYA endpoint untuk resource ini (read-only technical exposure).
// Tidak ada show/store/update/destroy — tidak ada CRUD untuk role manapun.

class HabitController extends Controller
{
    use ApiResponseTraits;

    public function __construct(protected HabitService $service) {}

    public function index()
    {
        $habits = $this->service->index();
        return $this->successResponse(HabitResource::collection($habits));
    }
}
