<?php

namespace App\Services\School;

use App\Exceptions\ApiException;
use App\Models\AcademicYear;
use App\Models\Headmaster;
use App\Models\Rombel;
use App\Models\StudentClassAssignment;
use App\Models\TeacherClassAssignment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RombelService
{
    /** F-19: field Rombel yang terkunci begitu Rombel punya assignment. */
    protected const LOCKED_FIELDS = ['academic_year_id', 'grade_level'];

    public function __construct(protected TeacherScopeService $teacherScope) {}

    protected function currentUser()
    {
        return auth('api')->user();
    }

    protected function schoolIdForUser($user): ?int
    {
        // F-14 residual: hanya profil AKTIF yang punya scope; selain itu 403 (pola R7).
        if ($user->role === 'headmaster') {
            return $user->activeHeadmasterSchoolId()
                ?? throw new HttpException(403, 'Profil Headmaster tidak aktif atau tidak ditemukan');
        }

        return $user->activeTeacherSchoolId()
            ?? throw new HttpException(403, 'Profil Teacher tidak aktif atau tidak ditemukan');
    }

    protected function assertAcademicYearBelongsToSchool(int $academicYearId, int $schoolId): void
    {
        $belongs = AcademicYear::where('id', $academicYearId)
            ->where('school_id', $schoolId)
            ->exists();

        if (!$belongs) {
            throw new ApiException('academic_year_mismatch', 422, 'Academic Year tidak dimiliki sekolah ini');
        }
    }

    /**
     * F-21: Query Rombel yang boleh dilihat user. Headmaster: seluruh Rombel sekolahnya (tidak
     * berubah). Teacher: HANYA Rombel dari active assignment miliknya (via TeacherScopeService,
     * satu-satunya sumber scope Teacher); tanpa active assignment -> tidak ada Rombel sama sekali.
     * Dipakai index() dan show() supaya detail tidak lebih longgar dari list.
     */
    protected function visibleRombels($user)
    {
        $schoolId = $this->schoolIdForUser($user); // F-14: profil inactive -> 403 lebih dulu

        $query = Rombel::where('school_id', $schoolId);

        if ($user->role === 'teacher') {
            $assignment = $this->teacherScope->activeAssignment($user);

            // whereRaw('1 = 0') alih-alih where('id', null): hasil kosong yang eksplisit.
            $assignment
                ? $query->where('id', $assignment->rombel_id)
                : $query->whereRaw('1 = 0');
        }

        return $query;
    }

    public function index()
    {
        return $this->visibleRombels($this->currentUser())->get();
    }

    public function show(int $id)
    {
        $rombel = $this->visibleRombels($this->currentUser())->find($id);

        if (!$rombel) {
            throw new HttpException(404, 'Rombel tidak ditemukan');
        }

        return $rombel;
    }

    public function store(array $data): Rombel
    {
        $schoolId = $this->schoolIdForUser($this->currentUser());

        if ((int) $data['school_id'] !== $schoolId) {
            throw new HttpException(403, 'Anda hanya boleh membuat rombel untuk sekolah sendiri');
        }

        $this->assertAcademicYearBelongsToSchool($data['academic_year_id'], $schoolId);

        return Rombel::create([
            'school_id'        => $schoolId,
            'academic_year_id' => $data['academic_year_id'],
            'name'             => $data['name'],
            'grade_level'      => $data['grade_level'],
        ]);
    }

    public function update(int $id, array $data): Rombel
    {
        $rombel = $this->findRombelOrFail($id);
        $this->assertRombelInScope($rombel, $this->currentUser());

        if (isset($data['academic_year_id'])) {
            $this->assertAcademicYearBelongsToSchool($data['academic_year_id'], $rombel->school_id);
        }

        // F-19: lock baris Rombel supaya cek assignment + update atomik terhadap PATCH lain.
        return DB::transaction(function () use ($rombel, $data) {
            $locked = Rombel::whereKey($rombel->getKey())->lockForUpdate()->firstOrFail();

            $this->assertLockedFieldsUnchangedIfAssigned($locked, $data);

            $locked->update($data);

            return $locked->fresh();
        });
    }

    /**
     * F-19 (keputusan FINAL Matrix v3.1): setelah Rombel punya assignment (Teacher ATAU Student,
     * aktif maupun historis) academic_year_id dan grade_level tidak boleh berubah lewat PATCH biasa,
     * karena menggeser Ranking Angkatan retroaktif dan merusak histori. Rombel tanpa assignment
     * tetap bebas diubah; PATCH field lain (name) tidak terpengaruh. Yang ditolak hanya nilai yang
     * BENAR-BENAR berbeda dari yang tersimpan, jadi klien yang mengirim ulang objek utuh dengan
     * nilai yang sama tidak kena 409. Seluruh PATCH ditolak (atomik): tidak ada perubahan parsial.
     */
    protected function assertLockedFieldsUnchangedIfAssigned(Rombel $rombel, array $data): void
    {
        $changed = [];
        foreach (self::LOCKED_FIELDS as $field) {
            if (array_key_exists($field, $data) && (int) $data[$field] !== (int) $rombel->{$field}) {
                $changed[] = $field;
            }
        }

        if ($changed === [] || !$this->rombelHasAnyAssignment($rombel->id)) {
            return;
        }

        throw new ApiException(
            'rombel_field_locked',
            409,
            'academic_year_id dan grade_level tidak dapat diubah karena Rombel sudah memiliki assignment',
            array_map(fn ($f) => ['field' => $f, 'reason' => 'rombel_field_locked', 'message' => 'Terkunci: Rombel sudah memiliki assignment'], $changed)
        );
    }

    protected function rombelHasAnyAssignment(int $rombelId): bool
    {
        return TeacherClassAssignment::where('rombel_id', $rombelId)->exists()
            || StudentClassAssignment::where('rombel_id', $rombelId)->exists();
    }

    public function destroy(int $id): void
    {
        $rombel = $this->findRombelOrFail($id);
        $this->assertRombelInScope($rombel, $this->currentUser());

        try {
            $rombel->delete();
        } catch (QueryException $e) {
            if ($this->isForeignKeyViolation($e)) {
                throw new ApiException('rombel_has_active_assignment', 409, 'Rombel masih memiliki assignment yang mereferensikannya');
            }

            throw $e;
        }
    }

    private function isForeignKeyViolation(QueryException $e): bool
    {
        return $e->getCode() === '23503'; // kode SQLSTATE PostgreSQL untuk foreign_key_violation
    }

    /**
     * Fetch GLOBAL (tanpa filter school_id) — dipakai WRITE ops (update/destroy),
     * beda dari show() yang tetap 404 untuk GET (§26, cegah enumerasi lintas-scope).
     * Write op scope-nya jelas dari path -> 403 kalau di luar sekolah (API Contract
     * §14: "403 — cross-school").
     */
    protected function findRombelOrFail(int $id): Rombel
    {
        $rombel = Rombel::find($id);

        if (!$rombel) {
            throw new HttpException(404, 'Rombel tidak ditemukan');
        }

        return $rombel;
    }

    protected function assertRombelInScope(Rombel $rombel, $user): void
    {
        if ($rombel->school_id !== $this->schoolIdForUser($user)) {
            throw new HttpException(403, 'Rombel ini di luar sekolah Anda');
        }
    }
}