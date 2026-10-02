<?php

namespace App\Services\School;

use App\Exceptions\ApiException;
use App\Models\Headmaster;
use App\Models\Rombel;
use App\Models\Teacher;
use App\Models\TeacherClassAssignment;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TeacherAssignmentService
{
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

    public function index()
    {
        $user = $this->currentUser();

        if ($user->role === 'headmaster') {
            return TeacherClassAssignment::where('school_id', $this->headmasterSchoolId($user))->get();
        }

        // Teacher: hanya lihat assignment miliknya sendiri (F-14 residual: hanya Teacher AKTIF)
        $teacherId = $user->activeTeacherId()
            ?? throw new HttpException(403, 'Profil Teacher tidak aktif atau tidak ditemukan');

        return TeacherClassAssignment::where('teacher_id', $teacherId)->get();
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
            throw new ApiException('teacher_already_has_active_assignment', 409, 'Guru sudah punya assignment aktif pada tahun ajaran ini');
        }

        return TeacherClassAssignment::create([
            'teacher_id'       => $data['teacher_id'],
            'school_id'        => $data['school_id'],
            'academic_year_id' => $data['academic_year_id'],
            'rombel_id'        => $data['rombel_id'],
            'is_active'        => true,
        ]);
    }

    public function update(int $id, array $data): TeacherClassAssignment
    {
        $assignment = $this->findAssignmentOrFail($id);
        $this->assertAssignmentInScope($assignment, $this->currentUser());

        if (isset($data['rombel_id'])) {
            $this->assertRombelMatchesSchoolAndYear($data['rombel_id'], $assignment->school_id, $assignment->academic_year_id);
            $assignment->update(['rombel_id' => $data['rombel_id']]);
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

        if ($user->role === 'teacher' && $assignment->teacher_id !== $user->activeTeacherId()) {
            throw new HttpException(403, 'Assignment ini bukan milik Anda');
        }
    }
}