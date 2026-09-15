<?php

namespace App\Http\Controllers\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\CreateStudentRequest;
use App\Http\Requests\Student\UpdateStudentRequest;
use App\Http\Resources\Student\StudentResource;
use App\Services\Student\StudentService;
use App\Traits\ApiResponseTraits;

class StudentController extends Controller
{
    use ApiResponseTraits;

    public function __construct(protected StudentService $service) {}

    public function index()
    {
        $students = $this->service->index();
        return $this->successResponse(StudentResource::collection($students), 'Daftar siswa berhasil diambil');
    }

    public function store(CreateStudentRequest $request)
    {
        $student = $this->service->store($request->validated());
        return $this->successResponse(new StudentResource($student), 'Siswa berhasil dibuat');
    }

    public function show(int $id)
    {
        $student = $this->service->show($id);
        return $this->successResponse(new StudentResource($student), 'Detail siswa berhasil diambil');
    }

    public function update(UpdateStudentRequest $request, int $id)
    {
        $student = $this->service->update($id, $request->validated());
        return $this->successResponse(new StudentResource($student), 'Siswa berhasil diperbarui');
    }

    public function destroy(int $id)
    {
        $this->service->destroy($id);
        return $this->successResponse(null, 'Akun siswa berhasil dinonaktifkan');
    }
}