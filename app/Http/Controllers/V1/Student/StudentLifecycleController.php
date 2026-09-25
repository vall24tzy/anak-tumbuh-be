<?php

namespace App\Http\Controllers\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\Student\StudentResource;
use App\Services\Student\StudentLifecycleService;
use App\Traits\ApiResponseTraits;

/**
 * STUDENT-LIFECYCLE-001 — API Contract v1.2 §20. Semua method Teacher only,
 * own active rombel (role:teacher di route). TIDAK ADA endpoint reactivation
 * dan TIDAK ADA hard delete di controller ini (Matrix #12, #41-43).
 */
class StudentLifecycleController extends Controller
{
    use ApiResponseTraits;

    public function __construct(protected StudentLifecycleService $service)
    {
    }

    public function inactive(int $id)
    {
        return $this->successResponse(new StudentResource($this->service->markInactive($id)));
    }

    public function graduate(int $id)
    {
        return $this->successResponse(new StudentResource($this->service->markGraduated($id)));
    }

    public function bulkGraduate(int $rombelId)
    {
        return $this->successResponse($this->service->bulkGraduate($rombelId));
    }
}
