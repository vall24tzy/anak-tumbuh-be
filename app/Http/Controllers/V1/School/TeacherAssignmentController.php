<?php

namespace App\Http\Controllers\V1\School;

use App\Http\Controllers\Controller;
use App\Http\Requests\Common\PaginationRequest;
use App\Http\Requests\School\CreateTeacherAssignmentRequest;
use App\Http\Requests\School\UpdateTeacherAssignmentRequest;
use App\Http\Resources\School\TeacherAssignmentResource;
use App\Services\School\TeacherAssignmentService;
use App\Traits\ApiResponseTraits;
use App\Traits\PaginatesResults;

class TeacherAssignmentController extends Controller
{
    use ApiResponseTraits, PaginatesResults;

    public function __construct(protected TeacherAssignmentService $service) {}

    // F-15: response {data, meta} lewat helper pagination bersama.
    public function index(PaginationRequest $request)
    {
        return $this->paginatedResponse(TeacherAssignmentResource::class, $this->service->index($request->validated()));
    }

    public function store(CreateTeacherAssignmentRequest $request)
    {
        $assignment = $this->service->store($request->validated());
        return $this->successResponse(new TeacherAssignmentResource($assignment), code: 201);
    }

    public function show(int $id)
    {
        return $this->successResponse(new TeacherAssignmentResource($this->service->show($id)));
    }

    public function update(UpdateTeacherAssignmentRequest $request, int $id)
    {
        $assignment = $this->service->update($id, $request->validated());
        return $this->successResponse(new TeacherAssignmentResource($assignment));
    }

    public function deactivate(int $id)
    {
        $assignment = $this->service->deactivate($id);
        return $this->successResponse(new TeacherAssignmentResource($assignment));
    }
}