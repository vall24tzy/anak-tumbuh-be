<?php

namespace App\Http\Controllers\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\CreateStudentClassAssignmentRequest;
use App\Http\Requests\Student\PaginatedListRequest;
use App\Http\Requests\Student\UpdateStudentClassAssignmentRequest;
use App\Http\Resources\Student\StudentClassAssignmentResource;
use App\Services\Student\StudentClassAssignmentService;
use App\Services\Student\StudentHabitService;
use App\Traits\ApiResponseTraits;

// API Contract v1.2 §19. update() mengikuti Keputusan Tim NOTE-05
// (docs/db-gap-analysis.md) — bukan spesifikasi literal API Contract v1.2,
// yang tidak merinci skema body PATCH. Hanya transisi status active->ended.

class StudentClassAssignmentController extends Controller
{
    use ApiResponseTraits;

    public function __construct(protected StudentClassAssignmentService $service) {}

    public function index(PaginatedListRequest $request)
    {
        $paginator = $this->service->index($request->validated());

        return $this->successResponse(
            StudentClassAssignmentResource::collection($paginator->getCollection()),
            meta: StudentHabitService::paginationMeta($paginator),
        );
    }

    public function store(CreateStudentClassAssignmentRequest $request)
    {
        $assignment = $this->service->store($request->validated());
        return $this->successResponse(new StudentClassAssignmentResource($assignment), code: 201);
    }

    public function update(UpdateStudentClassAssignmentRequest $request, int $id)
    {
        $assignment = $this->service->endAssignment($id);
        return $this->successResponse(new StudentClassAssignmentResource($assignment));
    }

    public function historyForStudent(int $id)
    {
        $history = $this->service->historyForStudent($id);
        return $this->successResponse(StudentClassAssignmentResource::collection($history));
    }
}
