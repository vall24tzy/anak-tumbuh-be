<?php

namespace App\Services\School;

use App\Models\School;
use App\Services\Upload\UploadService;
use App\Traits\ConvertsUniqueViolation;
use App\Traits\PaginatesResults;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SchoolService
{
    use ConvertsUniqueViolation, PaginatesResults;

    public function __construct(protected UploadService $uploadService) {}

    protected function currentUser()
    {
        return auth('api')->user();
    }

    protected function isSuperAdmin($user): bool
    {
        return $user->role === 'super_admin';
    }

    // F-10 (pola F-15): paginasi bersama (default 20, max 100, urutan id). Daftar global (Super Admin).
    public function index(array $params = [])
    {
        return $this->paginateQuery(School::withCount(['teachers', 'students']), $params);
    }

    public function store(array $data)
    {
        $user = $this->currentUser();

        if (!$this->isSuperAdmin($user)) {
            throw new HttpException(403, 'Hanya Super Admin yang boleh membuat sekolah baru');
        }

        $logoFile = $data['logo'] ?? null;
        unset($data['logo']);

        // F-11 (fallback balapan): `unique:schools,name` di request hanya menolak duplikat sekuensial.
        // Dua POST konkuren dengan nama sama sama-sama lolos validasi, lalu yang kedua menabrak
        // constraint DB schools_name_unique -> dulu 500 (atau, tanpa constraint, duplikat tersimpan).
        // Sekarang jadi 422 validation_failed pada field `name`, bentuk yang sama dengan duplikat biasa.
        $school = $this->convertUniqueViolation(fn () => DB::transaction(fn () => School::create($data)), [
            'schools_name_unique' => fn () => ValidationException::withMessages([
                'name' => [trans('validation.unique', ['attribute' => 'name'])],
            ]),
        ]);

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