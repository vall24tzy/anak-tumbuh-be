<?php

namespace App\Http\Controllers\V1\Gamification;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gamification\RankingRequest;
use App\Http\Resources\Gamification\RankingEntryResource;
use App\Services\Gamification\RankingService;
use App\Services\Student\StudentHabitService;
use App\Traits\ApiResponseTraits;

// API Contract v1.2 §24. Teacher & Student saja; Headmaster/Super Admin tidak
// punya ranking sebagai fitur khusus (Matrix #39) -> 403 dari role middleware.
// Meta pagination reuse StudentHabitService::paginationMeta() (KEPUTUSAN TIM
// revisi 3 — identik pola list habit).

class RankingController extends Controller
{
    use ApiResponseTraits;

    public function __construct(protected RankingService $service) {}

    public function rombel(RankingRequest $request)
    {
        $paginator = $this->service->rombel($request->validated());

        return $this->successResponse(
            RankingEntryResource::collection($paginator->getCollection()),
            meta: StudentHabitService::paginationMeta($paginator),
        );
    }

    public function angkatan(RankingRequest $request)
    {
        $paginator = $this->service->angkatan($request->validated());

        return $this->successResponse(
            RankingEntryResource::collection($paginator->getCollection()),
            meta: StudentHabitService::paginationMeta($paginator),
        );
    }
}
