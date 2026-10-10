<?php

namespace App\Services\Student;

use App\Exceptions\ApiException;
use App\Models\AcademicYear;
use App\Models\Headmaster;
use App\Models\Rombel;
use App\Models\Student;
use App\Models\StudentClassAssignment;
use App\Services\School\TeacherScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpKernel\Exception\HttpException;

// API Contract v1.2 §19, DB Design v1.5 §5.9. SATU-SATUNYA source of truth
// current placement + history Student. Method di sini dipakai ulang oleh
// STUDENT-001, IMPORT-001, STUDENT-LIFECYCLE-001 — JANGAN duplikasi query
// assignment di Service lain, selalu panggil method di sini.

class StudentClassAssignmentService
{
    public function __construct(protected TeacherScopeService $teacherScope) {}

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

    /**
     * GET /api/v1/student-class-assignments — scope sesuai Matrix #25:
     * SA global, HM school, Teacher active rombel, Student OWN.
     */
    /**
     * GET /student-class-assignments — dipaginasi (API Contract v1.2 §7: page,
     * per_page default 20, maksimum 100, meta). Scope per role TIDAK berubah.
     * orderBy('id') = urutan deterministik untuk pagination (detail teknis).
     */
    public function index(array $params = []): LengthAwarePaginator
    {
        $user = $this->currentUser();

        $query = match ($user->role) {
            'super_admin' => StudentClassAssignment::query(),
            'headmaster'  => StudentClassAssignment::where('school_id', $this->headmasterSchoolId($user)),
            'teacher'     => $this->indexQueryForTeacher($user),
            'student'     => StudentClassAssignment::where('student_id', $user->student?->id),
            default       => StudentClassAssignment::whereRaw('1 = 0'),
        };

        return $query->orderBy('id')->paginate(
            (int) ($params['per_page'] ?? 20),
            ['*'],
            'page',
            (int) ($params['page'] ?? 1),
        );
    }

    protected function indexQueryForTeacher($user): Builder
    {
        $assignment = $this->teacherScope->activeAssignment($user);

        if (!$assignment) {
            return StudentClassAssignment::whereRaw('1 = 0');
        }

        return StudentClassAssignment::where('rombel_id', $assignment->rombel_id);
    }

    /**
     * GET /api/v1/students/{id}/class-assignments — history lengkap per Student.
     * Scope mismatch -> 404 (resource existence leak, API Contract §26), bukan 403.
     */
    public function historyForStudent(int $studentId)
    {
        $user = $this->currentUser();
        $student = Student::find($studentId);

        if (!$student) {
            throw new HttpException(404, 'Student tidak ditemukan');
        }

        $visible = match ($user->role) {
            'super_admin' => true,
            'headmaster'  => $student->school_id === $this->headmasterSchoolId($user),
            'teacher'     => $this->studentInTeacherActiveRombel($user, $student),
            'student'     => $user->student?->id === $student->id,
            default       => false,
        };

        if (!$visible) {
            throw new HttpException(404, 'Student tidak ditemukan');
        }

        return $student->studentClassAssignments()->orderByDesc('created_at')->get();
    }

    /**
     * POST /api/v1/student-class-assignments — Teacher only, own active rombel.
     * Duplicate (rombel sama) vs conflict (rombel beda) ditentukan sebelum insert.
     */
    public function store(array $data): StudentClassAssignment
    {
        $user = $this->currentUser();
        $activeAssignment = $this->teacherScope->activeAssignment($user);

        if (!$activeAssignment) {
            throw new HttpException(403, 'Anda belum memiliki active rombel');
        }

        if ((int) $data['rombel_id'] !== $activeAssignment->rombel_id) {
            throw new HttpException(403, 'Anda hanya boleh membuat assignment pada own active rombel');
        }

        $student = Student::find($data['student_id']);
        if (!$student) {
            throw new HttpException(404, 'Student tidak ditemukan');
        }

        if ($student->school_id !== $activeAssignment->school_id) {
            throw new HttpException(403, 'Student di luar sekolah Anda');
        }

        $this->assertRombelMatchesAcademicYear((int) $data['rombel_id'], (int) $data['academic_year_id']);

        return $this->createAssignment(
            (int) $data['student_id'],
            (int) $data['rombel_id'],
            (int) $data['academic_year_id'],
        );
    }

    /**
     * Reusable inti: duplicate/conflict check + insert. Dipakai ulang oleh
     * STUDENT-001 (assignment awal saat Create Student) dan IMPORT-001
     * (assignment per baris valid) — scope check (Teacher own active rombel)
     * jadi tanggung jawab pemanggil masing-masing, method ini murni data layer.
     */
    public function createAssignment(int $studentId, int $rombelId, int $academicYearId): StudentClassAssignment
    {
        $existing = StudentClassAssignment::where('student_id', $studentId)
            ->where('academic_year_id', $academicYearId)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            if ($existing->rombel_id === $rombelId) {
                throw new ApiException('duplicate_assignment', 409, 'Student sudah punya assignment aktif pada rombel yang sama di tahun ajaran ini');
            }

            throw new ApiException('assignment_conflict', 409, 'Student sudah punya assignment aktif pada rombel lain di tahun ajaran ini');
        }

        $rombel = Rombel::findOrFail($rombelId);

        return StudentClassAssignment::create([
            'student_id'       => $studentId,
            'school_id'        => $rombel->school_id,
            'rombel_id'        => $rombelId,
            'academic_year_id' => $academicYearId,
            'status'           => 'active',
        ]);
    }

    /**
     * Reusable: menutup active assignment Student (status='ended'). Dipakai
     * STUDENT-LIFECYCLE-001 sebagai bagian transaksi inactive/graduate/bulk-graduate
     * (DB Design v1.5 §5.5 cascade) — TIDAK dipanggil dari service ini sendiri.
     */
    public function closeActiveAssignment(int $studentId): void
    {
        StudentClassAssignment::where('student_id', $studentId)
            ->where('status', 'active')
            ->update(['status' => 'ended']);
    }

    /**
     * PATCH /api/v1/student-class-assignments/{id} — Keputusan Tim (NOTE-05,
     * db-gap-analysis.md): satu-satunya efek yang diizinkan, transisi
     * active -> ended, TIDAK ADA auto-move, TIDAK ADA reactivation.
     * Scope: Teacher only, own active rombel. Idempotent bila sudah 'ended'.
     */
    public function endAssignment(int $id): StudentClassAssignment
    {
        $user = $this->currentUser();
        $activeAssignment = $this->teacherScope->activeAssignment($user);

        if (!$activeAssignment) {
            throw new HttpException(403, 'Anda belum memiliki active rombel');
        }

        $assignment = StudentClassAssignment::find($id);

        if (!$assignment) {
            throw new HttpException(404, 'Student Class Assignment tidak ditemukan');
        }

        // Write operation, scope jelas dari path -> 403 (API Contract §26),
        // BUKAN 404 seperti pola GET-by-id.
        if ($assignment->rombel_id !== $activeAssignment->rombel_id) {
            throw new HttpException(403, 'Assignment ini di luar own active rombel Anda');
        }

        if ($assignment->status === 'ended') {
            return $assignment; // idempotent, tidak ada efek tambahan
        }

        $assignment->update(['status' => 'ended']);

        return $assignment->fresh();
    }

    /**
     * Assignment aktif Student pada Academic Year yang sedang active di
     * school-nya (STUDENTHABIT-001, GAP #5). Deterministik: 1 active Academic
     * Year per school (DB Design §5.6) + 1 active assignment per (student,
     * academic year) (DB Design §5.9). Tidak ada fallback ke assignment
     * ended / Academic Year lain — bila tidak ada, return null.
     */
    public function activeAssignmentInCurrentAcademicYear(Student $student): ?StudentClassAssignment
    {
        return StudentClassAssignment::with('rombel')
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->whereIn(
                'academic_year_id',
                AcademicYear::where('school_id', $student->school_id)
                    ->where('is_active', true)
                    ->select('id')
            )
            ->first();
    }

    /**
     * GAP #5-scope (24 Sep 2026, db-gap-analysis.md — STUDENT-001 patch 7):
     * sebelumnya pakai $student->activeClassAssignment?->rombel_id (hasOne
     * tanpa urutan tahun, non-deterministik kalau Student punya >1 assignment
     * aktif lintas Academic Year — DB Design v1.5 §5.9). Diganti EXISTS check
     * via Student::hasActiveAssignmentInRombel() (app/Models/Student.php,
     * ditambah patch 7). Business rule scope TIDAK berubah, skema TIDAK diubah.
     */
    protected function studentInTeacherActiveRombel($user, Student $student): bool
    {
        $teacherRombelId = $this->teacherScope->activeAssignment($user)?->rombel_id;

        return $teacherRombelId !== null && $student->hasActiveAssignmentInRombel($teacherRombelId);
    }

    protected function assertRombelMatchesAcademicYear(int $rombelId, int $academicYearId): void
    {
        $ok = Rombel::where('id', $rombelId)->where('academic_year_id', $academicYearId)->exists();

        if (!$ok) {
            throw new ApiException('rombel_mismatch', 422, 'academic_year_id tidak sesuai dengan rombel yang diminta');
        }
    }
}
