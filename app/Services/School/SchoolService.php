<?php

namespace App\Services\School;

use App\Models\School;
use App\Services\Upload\UploadService;
use App\Services\User\AuditLogService;
use App\Traits\ConvertsUniqueViolation;
use App\Traits\PaginatesResults;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SchoolService
{
    use ConvertsUniqueViolation, PaginatesResults;

    public function __construct(
        protected UploadService $uploadService,
        protected AuditLogService $auditLog,
    ) {}

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

    /**
     * INTEG-SCHOOL-001 (D-09 FINAL, Matrix #65): ubah nama sekolah, Super Admin saja (guard ganda di
     * sini selain middleware, pola store()). Super Admin global -> tidak ada kasus lintas sekolah; id
     * tidak ada -> 404 (D-02). Hanya `name` yang sampai ke sini (UpdateSchoolRequest menolak sisanya).
     *
     * D-03 FINAL: aksi dicatat ke audit_logs lewat AuditLogService. D-15 FINAL: action `school_name_update`,
     * target_user_id null (D-03, F-31). D-14 FINAL: dicatat hanya bila nama BENAR-BENAR berubah; "sama" =
     * identik dengan nilai tersimpan setelah normalisasi input yang sama dengan saat menyimpan (trim oleh
     * middleware TrimStrings); beda huruf besar/kecil dihitung berubah. Nama yang sama = no-op: 200, tanpa
     * baris audit. Bila kelak semua PATCH harus tercatat, cukup ubah blok `isDirty` di bawah.
     */
    public function update(int $id, array $data)
    {
        $actor = $this->currentUser();

        if (!$this->isSuperAdmin($actor)) {
            throw new HttpException(403, 'Hanya Super Admin yang boleh mengubah nama sekolah');
        }

        $school = School::find($id);

        if (!$school) {
            throw new HttpException(404, 'Sekolah tidak ditemukan');
        }

        $school->name = $data['name'];

        if (!$school->isDirty('name')) {
            return $school;
        }

        // F-11 (fallback balapan): sama dengan store() — duplikat konkuren -> 422, bukan 500.
        $this->convertUniqueViolation(fn () => DB::transaction(fn () => $school->save()), [
            'schools_name_unique' => fn () => ValidationException::withMessages([
                'name' => [trans('validation.unique', ['attribute' => 'name'])],
            ]),
        ]);

        $this->auditLog->record($actor, 'school_name_update');

        return $school->fresh();
    }
}