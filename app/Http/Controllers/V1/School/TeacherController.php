<?php

namespace App\Http\Controllers\V1\School;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\CreateTeacherRequest;
use App\Http\Resources\School\TeacherResource;
use App\Services\School\TeacherService;
use App\Traits\ApiResponseTraits;

class TeacherController extends Controller
{
    use ApiResponseTraits;

    public function __construct(protected TeacherService $service) {}

    public function index()
    {
        $teachers = $this->service->index();
        return $this->successResponse(TeacherResource::collection($teachers));
    }

    public function store(CreateTeacherRequest $request)
    {
        $teacher = $this->service->store($request->validated());
        return $this->successResponse(new TeacherResource($teacher), code: 201);
    }

    public function show(int $id)
    {
        $teacher = $this->service->show($id);
        return $this->successResponse(new TeacherResource($teacher));
    }
}