<?php

namespace App\Http\Controllers\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\CreateStudentRequest;
use App\Http\Requests\Student\UpdateStudentRequest;
use App\Http\Resources\Student\StudentResource;
use App\Services\Student\StudentService;
use App\Traits\ApiResponseTraits;

// API Contract v1.2 §17. TIDAK ADA destroy() — Matrix #12: hard delete DENY
// semua role, lifecycle (inactive/graduated) di STUDENT-LIFECYCLE-001.

class StudentController extends Controller
{
    use ApiResponseTraits;

    public function __construct(protected StudentService $service) {}

    public function index()
    {
        return $this->successResponse(StudentResource::collection($this->service->index()));
    }

    public function store(CreateStudentRequest $request)
    {
        $student = $this->service->store($request->validated());
        return $this->successResponse(new StudentResource($student), code: 201);
    }

    public function show(int $id)
    {
        return $this->successResponse(new StudentResource($this->service->show($id)));
    }

    public function update(UpdateStudentRequest $request, int $id)
    {
        $student = $this->service->update($id, $request->validated());
        return $this->successResponse(new StudentResource($student));
    }
}
