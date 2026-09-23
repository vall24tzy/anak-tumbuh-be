<?php

namespace App\Http\Controllers\V1\School;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\CreateTeacherAssignmentRequest;
use App\Http\Requests\School\UpdateTeacherAssignmentRequest;
use App\Http\Resources\School\TeacherAssignmentResource;
use App\Services\School\TeacherAssignmentService;
use App\Traits\ApiResponseTraits;

class TeacherAssignmentController extends Controller
{
    use ApiResponseTraits;

    public function __construct(protected TeacherAssignmentService $service) {}

    public function index()
    {
        return $this->successResponse(TeacherAssignmentResource::collection($this->service->index()));
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