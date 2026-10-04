<?php

namespace App\Http\Controllers\V1\School;

use App\Http\Controllers\Controller;
use App\Http\Requests\Common\PaginationRequest;
use App\Http\Requests\School\CreateAcademicYearRequest;
use App\Http\Requests\School\UpdateAcademicYearRequest;
use App\Http\Resources\School\AcademicYearResource;
use App\Services\School\AcademicYearService;
use App\Traits\ApiResponseTraits;
use App\Traits\PaginatesResults;

class AcademicYearController extends Controller
{
    use ApiResponseTraits, PaginatesResults;

    public function __construct(protected AcademicYearService $service) {}

    // F-15: response {data, meta} lewat helper pagination bersama.
    public function index(PaginationRequest $request)
    {
        return $this->paginatedResponse(AcademicYearResource::class, $this->service->index($request->validated()));
    }

    public function store(CreateAcademicYearRequest $request)
    {
        $academicYear = $this->service->store($request->validated());
        return $this->successResponse(new AcademicYearResource($academicYear), code: 201);
    }

    public function show(int $id)
    {
        $academicYear = $this->service->show($id);
        return $this->successResponse(new AcademicYearResource($academicYear));
    }

    public function update(UpdateAcademicYearRequest $request, int $id)
    {
        $academicYear = $this->service->update($id, $request->validated());
        return $this->successResponse(new AcademicYearResource($academicYear));
    }

    public function activate(int $id)
    {
        $academicYear = $this->service->activate($id);
        return $this->successResponse(new AcademicYearResource($academicYear));
    }
}