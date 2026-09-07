<?php

namespace App\Http\Controllers\V1\School;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\CreateSchoolRequest;
use App\Http\Requests\School\UpdateSchoolRequest;
use App\Http\Resources\School\SchoolResource;
use App\Services\School\SchoolService;
use App\Traits\ApiResponseTraits;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SchoolController extends Controller
{
    use ApiResponseTraits;

    public function __construct(protected SchoolService $service) {}

    public function index()
    {
        $schools = $this->service->index();
        return $this->successResponse(SchoolResource::collection($schools), 'Daftar sekolah berhasil diambil');
    }

    public function store(CreateSchoolRequest $request)
    {
        try {
            $school = $this->service->store($request->validated());
            return $this->successResponse(new SchoolResource($school), 'Sekolah berhasil dibuat');
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), $e->getStatusCode());
        }
    }

    public function show(int $id)
    {
        try {
            $school = $this->service->show($id);
            return $this->successResponse(new SchoolResource($school), 'Detail sekolah berhasil diambil');
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), $e->getStatusCode());
        }
    }

    public function update(UpdateSchoolRequest $request, int $id)
    {
        try {
            $school = $this->service->update($id, $request->validated());
            return $this->successResponse(new SchoolResource($school), 'Sekolah berhasil diperbarui');
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), $e->getStatusCode());
        }
    }

    public function destroy(int $id)
    {
        try {
            $this->service->destroy($id);
            return $this->successResponse(null, 'Sekolah berhasil dihapus');
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), $e->getStatusCode());
        }
    }
}