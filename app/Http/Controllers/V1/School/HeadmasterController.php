<?php

namespace App\Http\Controllers\V1\School;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\CreateHeadmasterRequest;
use App\Http\Requests\School\UpdateHeadmasterRequest;
use App\Http\Resources\School\HeadmasterResource;
use App\Services\School\HeadmasterService;
use App\Traits\ApiResponseTraits;

class HeadmasterController extends Controller
{
    use ApiResponseTraits;

    public function __construct(protected HeadmasterService $service) {}

    public function index()
    {
        $headmasters = $this->service->index();
        return $this->successResponse(HeadmasterResource::collection($headmasters), 'Daftar Kepala Sekolah berhasil diambil');
    }

    public function store(CreateHeadmasterRequest $request)
    {
        [$headmaster, $generatedPassword] = $this->service->store($request->validated());

        $data = (new HeadmasterResource($headmaster))->resolve();
        $data['generated_password'] = $generatedPassword;
        $data['catatan'] = 'Simpan password ini sekarang — tidak akan ditampilkan lagi setelah ini.';

        return $this->successResponse($data, 'Kepala Sekolah berhasil dibuat');
    }

    public function show(int $id)
    {
        $headmaster = $this->service->show($id);
        return $this->successResponse(new HeadmasterResource($headmaster), 'Detail Kepala Sekolah berhasil diambil');
    }

    public function update(UpdateHeadmasterRequest $request, int $id)
    {
        $headmaster = $this->service->update($id, $request->validated());
        return $this->successResponse(new HeadmasterResource($headmaster), 'Kepala Sekolah berhasil diperbarui');
    }

    public function destroy(int $id)
    {
        $this->service->destroy($id);
        return $this->successResponse(null, 'Akun Kepala Sekolah berhasil dinonaktifkan');
    }
}