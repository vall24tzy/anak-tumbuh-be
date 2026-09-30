<?php

namespace App\Http\Controllers\V1\Gamification;

use App\Http\Controllers\Controller;
use App\Http\Resources\Gamification\GamificationResource;
use App\Services\Gamification\GamificationService;
use App\Traits\ApiResponseTraits;

// API Contract v1.2 §23. Read-only — tidak ada endpoint write untuk Level/poin
// (Inisiatif +10 SYSTEM, threshold FIXED tanpa CRUD).

class GamificationController extends Controller
{
    use ApiResponseTraits;

    public function __construct(protected GamificationService $service) {}

    public function me()
    {
        return $this->successResponse(new GamificationResource($this->service->me()));
    }

    public function forStudent(int $id)
    {
        return $this->successResponse(new GamificationResource($this->service->forStudent($id)));
    }
}
