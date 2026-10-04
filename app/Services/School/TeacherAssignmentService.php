<?php

namespace App\Services\School;

use App\Exceptions\ApiException;
use App\Models\Headmaster;
use App\Models\Rombel;
use App\Models\Teacher;
use App\Models\TeacherClassAssignment;
use App\Traits\ConvertsUniqueViolation;
use App\Traits\PaginatesResults;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TeacherAssignmentService
{
    use ConvertsUniqueViolation, PaginatesResults;

    protected function currentUser()
    {
        return auth('api')->user();
    }

    protected function headmasterSchoolId($user): ?int
    {
        // F-14 residual: hanya Headmaster AKTIF yang punya scope; selain itu 403 (pola R7).
        return $user->activeHeadmasterSchoolId()
            ?? throw new HttpException(403, 'Profil Headmaster tidak aktif atau tidak ditemukan');
    }

    // F-15: paginasi bersama (default 20, max 100, urutan id). total = assignment dalam scope user
    // (Headmaster: sekolahnya; Teacher: miliknya sendiri), scope SEBELUM paginasi.
    public function index(array $params = [])
    {
        $user = $this->currentUser();

        if ($user->role === 'headmaster') {
            return $this->paginateQuery(
                TeacherClassAssignment::where('school_id', $this->headmasterSchoolId($user)),
                $params
            );
        }

        // Teacher: hanya lihat assignment miliknya sendiri (F-14 residual: hanya Teacher AKTIF)
        $teacherId = $user->activeTeacherId()
            ?? throw new HttpException(403, 'Profil Teacher tidak aktif atau tidak ditemukan');

        return $this->paginateQuery(TeacherClassAssignment::where('teacher_id', $teacherId), $params);
    }

    public function show(int $id)
    {
        $user = $this->currentUser();
        $assignment = TeacherClassAssignment::find($id);

        if (!$assignment) {
            throw new HttpException(404, 'Teacher Class Assignment tidak ditemukan');
        }

        if ($user->role === 'headmaster' && $assignment->school_id !== $this->headmasterSchoolId($user)) {
            throw new HttpException(404, 'Teacher Class Assignment tidak ditemukan');
        }

        if ($user->role === 'teacher' && $assignment->teacher_id !== $user->activeTeacherId()) {
            throw new HttpException(404, 'Teacher Class Assignment tidak ditemukan');
        }

        return $assignment;
    }

    public function store(array $data): TeacherClassAssignment
    {
        $headmasterSchoolId = $this->headmasterSchoolId($this->currentUser());

        if ((int) $data['school_id'] !== $headmasterSchoolId) {
            throw new HttpException(403, 'Anda hanya boleh mengelola assignment di sekolah sendiri');
        }

        $teacherOk = Teacher::where('id', $data['teacher_id'])->where('school_id', $headmasterSchoolId)->exists();
        if (!$teacherOk) {
            throw new HttpException(403, 'Guru tidak berada di sekolah ini');
        }

        $this->assertRombelMatchesSchoolAndYear($data['rombel_id'], $data['school_id'], $data['academic_year_id']);

        $hasActive = TeacherClassAssignment::where('teacher_id', $data['teacher_id'])
            ->where('academic_year_id', $data['academic_year_id'])
            ->where('is_active', true)
            ->exists();

        if ($hasActive) {
            throw $this->teacherAlreadyActive();
        }

        // F-18: satu Rombel maksimal satu Teacher aktif per Academic Year (Teacher itu = wali kelas).
        $this->assertRombelHasNoOtherActiveTeacher($data['rombel_id'], $data['academic_year_id']);

        // F-16 + F-18 (fallback balapan): pre-check di atas hanya menutup kasus sekuensial. Dua POST
        // konkuren sama-sama lolos pre-check lalu yang kedua menabrak partial unique index
        // (Teacher+AY: uq_tca_one_active_per_teacher_year; Rombel+AY: uq_tca_one_active_per_rombel_year)
        // -> dulu 500, sekarang 409 dengan kode yang sama dengan jalur sekuensial.
        return $this->convertUniqueViolation(fn () => DB::transaction(fn () => TeacherClassAssignment::create([
            'teacher_id'       => $data['teacher_id'],
            'school_id'        => $data['school_id'],
            'academic_year_id' => $data['academic_year_id'],
            'rombel_id'        => $data['rombel_id'],
            'is_active'        => true,
        ])), $this->uniqueConflictMap());
    }

    public function update(int $id, array $data): TeacherClassAssignment
    {
        $assignment = $this->findAssignmentOrFail($id);
        $this->assertAssignmentInScope($assignment, $this->currentUser());

        // F-17: assignment historis (is_active=false) tidak boleh diedit, supaya scope/rombel tidak
        // bisa dipindah secara retroaktif. Perlu perubahan -> buat assignment baru (POST).
        // Dicek SETELAH scope (403 lintas sekolah tetap menang) dan SEBELUM validasi rombel.
        if (!$assignment->is_active) {
            throw new ApiException(
                'assignment_inactive_readonly',
                409,
                'Assignment yang sudah nonaktif (histori) tidak dapat diubah; buat assignment baru'
            );
        }

        if (isset($data['rombel_id'])) {
            $this->assertRombelMatchesSchoolAndYear($data['rombel_id'], $assignment->school_id, $assignment->academic_year_id);
            // F-18: memindahkan assignment ke rombel yang sudah punya Teacher aktif lain ditolak.
            $this->assertRombelHasNoOtherActiveTeacher($data['rombel_id'], $assignment->academic_year_id, $assignment->id);

            $this->convertUniqueViolation(
                fn () => DB::transaction(fn () => $assignment->update(['rombel_id' => $data['rombel_id']])),
                $this->uniqueConflictMap()
            );
        }

        return $assignment->fresh();
    }

    public function deactivate(int $id): TeacherClassAssignment
    {
        $assignment = $this->findAssignmentOrFail($id);
        $this->assertAssignmentInScope($assignment, $this->currentUser());

        $assignment->update(['is_active' => false]);

        return $assignment->fresh();
    }

    /**
     * F-18: pre-check sekuensial. $exceptAssignmentId dikecualikan supaya PATCH ke rombel yang
     * sama dengan miliknya sendiri (no-op) tidak dianggap bentrok dengan dirinya.
     */
    protected function assertRombelHasNoOtherActiveTeacher(int $rombelId, int $academicYearId, ?int $exceptAssignmentId = null): void
    {
        $taken = TeacherClassAssignment::where('rombel_id', $rombelId)
            ->where('academic_year_id', $academicYearId)
            ->where('is_active', true)
            ->when($exceptAssignmentId, fn ($q) => $q->whereKeyNot($exceptAssignmentId))
            ->exists();

        if ($taken) {
            throw $this->rombelAlreadyHasActiveTeacher();
        }
    }

    /** Peta constraint -> exception, dipakai fallback balapan di store() dan update(). */
    protected function uniqueConflictMap(): array
    {
        return [
            'uq_tca_one_active_per_teacher_year' => fn () => $this->teacherAlreadyActive(),
            'uq_tca_one_active_per_rombel_year'  => fn () => $this->rombelAlreadyHasActiveTeacher(),
        ];
    }

    protected function teacherAlreadyActive(): ApiException
    {
        return new ApiException('teacher_already_has_active_assignment', 409, 'Guru sudah punya assignment aktif pada tahun ajaran ini');
    }

    protected function rombelAlreadyHasActiveTeacher(): ApiException
    {
        return new ApiException('rombel_already_has_active_teacher', 409, 'Rombel ini sudah punya Teacher aktif pada tahun ajaran ini');
    }

    protected function assertRombelMatchesSchoolAndYear(int $rombelId, int $schoolId, int $academicYearId): void
    {
        $ok = Rombel::where('id', $rombelId)
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->exists();

        if (!$ok) {
            throw new ApiException('rombel_mismatch', 422, 'Rombel tidak sesuai sekolah/tahun ajaran yang diminta');
        }
    }

    protected function findAssignmentOrFail(int $id): TeacherClassAssignment
    {
        $assignment = TeacherClassAssignment::find($id);

        if (!$assignment) {
            throw new HttpException(404, 'Teacher Class Assignment tidak ditemukan');
        }

        return $assignment;
    }

    protected function assertAssignmentInScope(TeacherClassAssignment $assignment, $user): void
    {
        if ($user->role === 'headmaster' && $assignment->school_id !== $this->headmasterSchoolId($user)) {
            throw new HttpException(403, 'Assignment ini di luar sekolah Anda');
        }

        // F-50: cabang `role === 'teacher'` dihapus (kode mati). assertAssignmentInScope() hanya
        // dipanggil dari update()/deactivate(), yang rute-nya `role:headmaster` saja.
    }
}