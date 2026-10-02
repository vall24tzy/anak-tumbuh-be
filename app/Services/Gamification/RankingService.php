<?php

namespace App\Services\Gamification;

use App\Models\AcademicYear;
use App\Models\Rombel;
use App\Models\StudentClassAssignment;
use App\Models\Teacher;
use App\Services\School\TeacherScopeService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\HttpException;

// API Contract v1.2 §24, Dokumen v4.0 §9, Matrix #39. Ranking ASSIGNMENT-BASED:
// dibangun dari student_class_assignments (status='active') + rombels.grade_level/
// name, BUKAN dari string classroom.
//
// KEPUTUSAN TIM (revisi 3, lihat docs/db-gap-analysis.md NOTE-11 & addendum
// Level-Ranking):
// - Ranking HANYA untuk Academic Year AKTIF sekolah caller — academic_year_id
//   selain itu (termasuk tahun lain milik sekolah sendiri) DITOLAK 403 untuk
//   SEMUA role, bukan cuma cross-school.
// - Urutan pengecekan (A.4): academic_year_id lalu rombel_id divalidasi SEBELUM
//   hasil-kosong diputuskan — berlaku sama untuk Teacher & Student, terlepas
//   caller sedang punya active rombel atau tidak.
// - Caller tanpa konteks kelas (Teacher tanpa active assignment / Student tanpa
//   active assignment di tahun aktif) + rombel_id apa pun -> 403 juga (asumsi
//   tim: tidak ada rombel yang termasuk scope-nya untuk dicocokkan).
// - Response per baris: student_id, name, rombel, poin, level (bukan
//   "gamification_level" — beda nama dari §23, KEPUTUSAN TIM, lihat addendum).
//   Menampilkan name adalah pengecualian sadar terhadap "no classmates list"
//   (Dokumen v4.0 §9 / Matrix #9) — lihat addendum poin 6.

class RankingService
{
    public function __construct(
        protected TeacherScopeService $teacherScope,
        protected LevelService $levelService,
    ) {}

    protected function currentUser()
    {
        return auth('api')->user();
    }

    /** GET /api/v1/rankings/rombel */
    public function rombel(array $params): LengthAwarePaginator
    {
        $rombelId = isset($params['rombel_id']) ? (int) $params['rombel_id'] : null;
        $academicYearId = isset($params['academic_year_id']) ? (int) $params['academic_year_id'] : null;

        $rombel = $this->resolveOwnRombel($rombelId, $academicYearId);

        $query = $this->baseQuery();

        if ($rombel) {
            $query->where('student_class_assignments.rombel_id', $rombel->id);
        } else {
            $query->whereRaw('1 = 0'); // hasil kosong — pola sama dgn StudentHabitService
        }

        return $this->paginateWithLevel($query, $params);
    }

    /** GET /api/v1/rankings/angkatan (rombel_id tidak dipakai di endpoint ini) */
    public function angkatan(array $params): LengthAwarePaginator
    {
        $academicYearId = isset($params['academic_year_id']) ? (int) $params['academic_year_id'] : null;

        $rombel = $this->resolveOwnRombel(null, $academicYearId);

        $query = $this->baseQuery();

        if ($rombel) {
            $query->where('rombels.school_id', $rombel->school_id)
                ->where('rombels.grade_level', $rombel->grade_level)
                ->where('rombels.academic_year_id', $rombel->academic_year_id);
        } else {
            $query->whereRaw('1 = 0');
        }

        return $this->paginateWithLevel($query, $params);
    }

    /**
     * Sort: poin DESC, fullname ASC (business-facing), id ASC — tie-break
     * TEKNIS terakhir supaya pagination deterministik bila poin & nama sama
     * (bukan business rule baru, lihat addendum).
     */
    protected function baseQuery(): Builder
    {
        return StudentClassAssignment::query()
            ->join('students', 'students.id', '=', 'student_class_assignments.student_id')
            ->join('rombels', 'rombels.id', '=', 'student_class_assignments.rombel_id')
            ->where('student_class_assignments.status', 'active')
            ->orderByDesc('students.poin')
            ->orderBy('students.fullname')
            ->orderBy('students.id')
            ->select(
                'students.id as student_id',
                'students.fullname as name',
                'rombels.name as rombel',
                'students.poin as poin',
            );
    }

    /**
     * Pagination §7 (page/per_page/meta) — reuse StudentHabitService::paginationMeta()
     * di Controller. Level dihitung batch (LevelService::levelsForPoins) — 1 query
     * gamification_levels per request ranking, BUKAN per baris.
     */
    protected function paginateWithLevel(Builder $query, array $params): LengthAwarePaginator
    {
        $paginator = $query->paginate(
            (int) ($params['per_page'] ?? 20),
            ['*'],
            'page',
            (int) ($params['page'] ?? 1),
        );

        $levels = $this->levelService->levelsForPoins(
            $paginator->getCollection()->pluck('poin')->all()
        );

        $paginator->getCollection()->transform(function ($row, $i) use ($levels) {
            $row->level = $levels[$i] ?? 0;

            return $row;
        });

        return $paginator;
    }

    /**
     * A.4 — urutan WAJIB: (1) sekolah caller, (2) academic_year_id vs tahun
     * aktif sekolah itu, BARU (3) resolve rombel caller, (4) rombel_id vs
     * rombel caller. Rombel null (caller belum/tidak punya konteks kelas)
     * BUKAN alasan melewati langkah (2) atau (4).
     */
    protected function resolveOwnRombel(?int $rombelId, ?int $academicYearId): ?Rombel
    {
        $user = $this->currentUser();

        $schoolId = match ($user->role) {
            'teacher' => $user->activeTeacherSchoolId(),
            'student' => $user->student?->school_id,
            default   => throw new HttpException(403, 'Role Anda tidak memiliki akses ke ranking'),
        };

        if ($schoolId === null) {
            // Anomali data (profil Teacher/Student tidak ada) — tidak ada
            // sekolah untuk divalidasi, hasil kosong (bukan error).
            return null;
        }

        $activeYearId = AcademicYear::where('school_id', $schoolId)->where('is_active', true)->value('id');

        if ($academicYearId !== null && $academicYearId !== $activeYearId) {
            throw new HttpException(403, 'academic_year_id di luar scope Anda (hanya Academic Year aktif)');
        }

        $rombel = match ($user->role) {
            'teacher' => $this->teacherRombel($user),
            'student' => $this->studentRombel($user, $activeYearId),
        };

        if ($rombelId !== null && (!$rombel || $rombelId !== $rombel->id)) {
            throw new HttpException(403, 'rombel_id di luar scope Anda');
        }

        return $rombel;
    }

    /** null (bukan throw) kalau Teacher belum punya active assignment. */
    protected function teacherRombel($user): ?Rombel
    {
        $assignment = $this->teacherScope->activeAssignment($user);

        return $assignment ? Rombel::find($assignment->rombel_id) : null;
    }

    /** null kalau tidak ada Academic Year aktif, tidak ada profil Student, atau belum punya active assignment di tahun itu. */
    protected function studentRombel($user, ?int $activeYearId): ?Rombel
    {
        if ($activeYearId === null) {
            return null;
        }

        $student = $user->student;

        if (!$student) {
            return null;
        }

        $assignment = StudentClassAssignment::where('student_id', $student->id)
            ->where('status', 'active')
            ->where('academic_year_id', $activeYearId)
            ->first();

        return $assignment ? Rombel::find($assignment->rombel_id) : null;
    }
}
