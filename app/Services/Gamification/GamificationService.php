<?php

namespace App\Services\Gamification;

use App\Models\Headmaster;
use App\Models\Student;
use App\Services\School\TeacherScopeService;
use Symfony\Component\HttpKernel\Exception\HttpException;

// API Contract v1.2 §23, Matrix #38. Scope: SA global, HM own school, Teacher
// own active rombel, Student OWN. Scope mismatch di GET-by-id -> 404 (§26).

class GamificationService
{
    public function __construct(
        protected LevelService $levelService,
        protected TeacherScopeService $teacherScope,
    ) {}

    protected function currentUser()
    {
        return auth('api')->user();
    }

    /**
     * GET /api/v1/gamification/me — Student OWN. (Perilaku untuk role non-Student
     * belum dirinci SoT — lihat NOTE-11 docs/db-gap-analysis.md; route dibatasi
     * role:student.)
     */
    public function me(): array
    {
        $student = $this->currentUser()->student;

        if (!$student) {
            throw new HttpException(404, 'Siswa tidak ditemukan');
        }

        return $this->payload($student);
    }

    /**
     * GET /api/v1/students/{id}/gamification — SA, HM, Teacher.
     */
    public function forStudent(int $studentId): array
    {
        $user = $this->currentUser();
        $student = Student::find($studentId);

        if (!$student || !$this->canView($user, $student)) {
            throw new HttpException(404, 'Siswa tidak ditemukan');
        }

        return $this->payload($student);
    }

    protected function canView($user, Student $student): bool
    {
        return match ($user->role) {
            'super_admin' => true,
            'headmaster'  => $student->school_id === Headmaster::where('user_id', $user->id)->value('school_id'),
            'teacher'     => $this->studentInTeacherActiveRombel($user, $student),
            default       => false,
        };
    }

    protected function studentInTeacherActiveRombel($user, Student $student): bool
    {
        $rombelId = $this->teacherScope->activeAssignment($user)?->rombel_id;

        return $rombelId !== null && $student->hasActiveAssignmentInRombel($rombelId);
    }

    protected function payload(Student $student): array
    {
        return [
            'student_id'         => $student->id,
            'poin'               => (int) $student->poin,
            'gamification_level' => $this->levelService->levelForPoin((int) $student->poin),
        ];
    }
}
