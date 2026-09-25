<?php

namespace App\Services\Student;

use App\Models\Rombel;
use App\Models\Student;
use App\Models\StudentClassAssignment;
use App\Services\School\TeacherScopeService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * STUDENT-LIFECYCLE-001 — API Contract v1.2 §20, Dokumen v4.0 §6.3,
 * Matrix #41-43. Teacher only, own active rombel. TIDAK ADA reactivation,
 * TIDAK ADA promote/kenaikan kelas (KENAIKAN-001 REMOVE), TIDAK ADA hard
 * delete (Matrix #12) — status hanya bergerak maju: active -> inactive/graduated.
 *
 * Reuse: TeacherScopeService::activeAssignment() (TEACHER-ASSIGNMENT-001) dan
 * StudentClassAssignmentService::closeActiveAssignment() (STUDENT-ASSIGNMENT-001)
 * — tidak duplikasi query scope/assignment.
 *
 * Keputusan Tim NOTE-07 (24 Sep 2026, docs/db-gap-analysis.md) — idempotency
 * vs authorization: scope Teacher atas Student TETAP berbasis active
 * assignment untuk transisi state yang sungguhan terjadi (StudentService::
 * studentInTeacherActiveRombel() TIDAK diubah/disentuh, GET/PATCH biasa TIDAK
 * ikut melonggar). KHUSUS untuk endpoint lifecycle ini: kalau Student SUDAH
 * berada di target state, request idempotent 200 tanpa butuh activeClassAssignment
 * lagi — tapi tetap wajib lolos school-scope check (bukan dibuka penuh) supaya
 * tidak bocor lintas sekolah.
 */
class StudentLifecycleService
{
    public function __construct(
        protected TeacherScopeService $teacherScope,
        protected StudentClassAssignmentService $assignmentService,
    ) {
    }

    protected function currentUser()
    {
        return auth('api')->user();
    }

    /**
     * POST /api/v1/students/{id}/inactive — Matrix #41.
     */
    public function markInactive(int $id): Student
    {
        return $this->transition($id, 'inactive');
    }

    /**
     * POST /api/v1/students/{id}/graduate — Matrix #42. Identity tetap
     * disimpan (Dokumen v4.0 §6.3) — tidak ada hard delete di jalur mana pun.
     */
    public function markGraduated(int $id): Student
    {
        return $this->transition($id, 'graduated');
    }

    /**
     * NOTE-07: cek idempotent DULU (school-scope saja, tanpa
     * activeClassAssignment) sebelum cek scope active-rombel penuh — supaya
     * pemanggilan ulang pada Student yang assignment-nya sudah 'ended' tetap
     * 200 tanpa error, sesuai requirement idempotent Teacher Lifecycle
     * (Matrix #61) yang dianalogikan ke sini.
     */
    protected function transition(int $id, string $targetStatus): Student
    {
        $user = $this->currentUser();
        $student = Student::find($id);

        if (!$student) {
            throw new HttpException(404, 'Siswa tidak ditemukan.');
        }

        if ($student->status === $targetStatus) {
            if (!$this->sameSchoolAsTeacher($user, $student)) {
                throw new HttpException(403, 'Siswa ini di luar sekolah Anda.');
            }

            return $student; // idempotent, tanpa efek tambahan
        }

        $activeAssignment = $this->teacherScope->activeAssignment($user);

        if (!$activeAssignment) {
            throw new HttpException(403, 'Anda belum memiliki active rombel.');
        }

        // GAP #5-scope (24 Sep 2026, STUDENT-001 patch 7): sebelumnya
        // $student->activeClassAssignment?->rombel_id (hasOne ambigu lintas
        // Academic Year, DB Design §5.9). Diganti EXISTS check via
        // Student::hasActiveAssignmentInRombel() — NOTE-07 (idempotency
        // check di atas, sebelum blok ini) TIDAK disentuh/diubah urutannya.
        if (!$student->hasActiveAssignmentInRombel($activeAssignment->rombel_id)) {
            throw new HttpException(403, 'Siswa ini di luar own active rombel Anda.');
        }

        return DB::transaction(function () use ($student, $targetStatus) {
            $student->update(['status' => $targetStatus]);
            $this->assignmentService->closeActiveAssignment($student->id);

            return $student->fresh();
        });
    }

    protected function sameSchoolAsTeacher($user, Student $student): bool
    {
        return $student->school_id === $user->teacher?->school_id;
    }

    /**
     * POST /api/v1/rombels/{id}/bulk-graduate — Matrix #43. Rombel harus
     * own active rombel Teacher. Idempotent otomatis (tanpa logic tambahan):
     * panggilan kedua tidak menemukan assignment aktif lagi di rombel ini ->
     * total_students/graduated_count = 0, bukan error.
     */
    public function bulkGraduate(int $rombelId): array
    {
        $user = $this->currentUser();
        $rombel = Rombel::find($rombelId);

        if (!$rombel) {
            throw new HttpException(404, 'Rombel tidak ditemukan.');
        }

        $activeAssignment = $this->teacherScope->activeAssignment($user);

        if (!$activeAssignment || $activeAssignment->rombel_id !== $rombel->id) {
            throw new HttpException(403, 'Rombel ini bukan own active rombel Anda.');
        }

        return DB::transaction(function () use ($rombelId) {
            $studentIds = StudentClassAssignment::where('rombel_id', $rombelId)
                ->where('status', 'active')
                ->pluck('student_id');

            $graduated = 0;

            foreach ($studentIds as $studentId) {
                Student::where('id', $studentId)->update(['status' => 'graduated']);
                $this->assignmentService->closeActiveAssignment($studentId);
                $graduated++;
            }

            return [
                'rombel_id'       => $rombelId,
                'total_students'  => $studentIds->count(),
                'graduated_count' => $graduated,
            ];
        });
    }
}
