<?php

namespace App\Http\Controllers\V1\School;

use App\Http\Controllers\Controller;
use App\Http\Requests\Common\PaginationRequest;
use App\Http\Requests\School\CreateSchoolRequest;
use App\Http\Requests\School\UpdateSchoolRequest;
use App\Http\Resources\School\SchoolResource;
use App\Services\School\SchoolService;
use App\Traits\ApiResponseTraits;
use App\Traits\PaginatesResults;

class SchoolController extends Controller
{
    use ApiResponseTraits, PaginatesResults;

    public function __construct(protected SchoolService $service) {}

    // F-10 (pola F-15): response {data, meta} lewat helper pagination bersama.
    public function index(PaginationRequest $request)
    {
        return $this->paginatedResponse(
            SchoolResource::class,
            $this->service->index($request->validated()),
            'Daftar sekolah berhasil diambil'
        );
    }

    public function store(CreateSchoolRequest $request)
    {
        $school = $this->service->store($request->validated());
        return $this->successResponse(new SchoolResource($school), 'Sekolah berhasil dibuat', 201);
    }

    public function show(int $id)
    {
        $school = $this->service->show($id);
        return $this->successResponse(new SchoolResource($school), 'Detail sekolah berhasil diambil');
    }

    // INTEG-SCHOOL-001 (D-09, Matrix #65): ubah nama sekolah, Super Admin saja.
    public function update(UpdateSchoolRequest $request, int $id)
    {
        $school = $this->service->update($id, $request->validated());
        return $this->successResponse(new SchoolResource($school), 'Nama sekolah berhasil diubah');
    }
}