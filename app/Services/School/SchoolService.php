<?php

namespace App\Services\School;

use App\Models\School;
use App\Services\Upload\UploadService;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SchoolService
{
    public function __construct(protected UploadService $uploadService) {}

    protected function currentUser()
    {
        return auth('api')->user();
    }

    protected function isSuperAdmin($user): bool
    {
        return $user->role === 'super_admin';
    }

    public function index()
    {
        return School::withCount(['teachers', 'students'])->get();
    }

    public function store(array $data)
    {
        $user = $this->currentUser();

        if (!$this->isSuperAdmin($user)) {
            throw new HttpException(403, 'Hanya Super Admin yang boleh membuat sekolah baru');
        }

        $logoFile = $data['logo'] ?? null;
        unset($data['logo']);

        $school = School::create($data);

        if ($logoFile instanceof UploadedFile) {
            $path = $this->uploadService->upload($logoFile, 'logos', $school->id);
            $school->update(['logo_path' => $path]);
        }

        return $school->fresh();
    }

    public function show(int $id)
    {
        $school = School::withCount(['teachers', 'students'])->find($id);

        if (!$school) {
            throw new HttpException(404, 'Sekolah tidak ditemukan');
        }

        return $school;
    }
}