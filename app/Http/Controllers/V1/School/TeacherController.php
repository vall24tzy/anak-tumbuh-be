<?php

namespace App\Http\Controllers\V1\School;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\CreateTeacherRequest;
use App\Http\Requests\School\UpdateTeacherRequest;
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
        return $this->successResponse(TeacherResource::collection($teachers), 'Daftar guru berhasil diambil');
    }

    public function store(CreateTeacherRequest $request)
    {
        [$teacher, $generatedPassword] = $this->service->store($request->validated());

        $data = (new TeacherResource($teacher))->resolve();
        $data['generated_password'] = $generatedPassword;
        $data['catatan'] = 'Simpan password ini sekarang — tidak akan ditampilkan lagi setelah ini.';

        return $this->successResponse($data, 'Guru berhasil dibuat');
    }

    public function show(int $id)
    {
        $teacher = $this->service->show($id);
        return $this->successResponse(new TeacherResource($teacher), 'Detail guru berhasil diambil');
    }

    public function update(UpdateTeacherRequest $request, int $id)
    {
        $teacher = $this->service->update($id, $request->validated());
        return $this->successResponse(new TeacherResource($teacher), 'Guru berhasil diperbarui');
    }

    public function destroy(int $id)
    {
        $this->service->destroy($id);
        return $this->successResponse(null, 'Akun guru berhasil dinonaktifkan');
    }
}