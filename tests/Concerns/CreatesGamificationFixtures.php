<?php

namespace Tests\Concerns;

use App\Models\AcademicYear;
use App\Models\Headmaster;
use App\Models\Rombel;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentClassAssignment;
use App\Models\Teacher;
use App\Models\TeacherClassAssignment;
use App\Models\User;

// Setup pakai Model::create() berantai (repo belum punya factory untuk chain ini),
// konsisten dengan tests/Feature/Student/*.
trait CreatesGamificationFixtures
{
    protected int $seq = 0;

    protected function makeSchool(string $name = 'SD Contoh'): array
    {
        $school = School::create(['name' => $name]);

        $year = AcademicYear::create([
            'school_id'  => $school->id,
            'name'       => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date'   => '2027-06-30',
            'is_active'  => true,
        ]);

        return [$school, $year];
    }

    protected function makeRombel(School $school, AcademicYear $year, string $name, int $grade): Rombel
    {
        return Rombel::create([
            'school_id'        => $school->id,
            'academic_year_id' => $year->id,
            'name'             => $name,
            'grade_level'      => $grade,
        ]);
    }

    protected function makeTeacher(School $school, AcademicYear $year, Rombel $rombel): User
    {
        $n = ++$this->seq;
        $nip = sprintf('1990%014d', $n);

        $user = User::create(['role' => 'teacher', 'username' => $nip, 'password' => 'secret']);

        $teacher = Teacher::create([
            'user_id' => $user->id, 'school_id' => $school->id, 'fullname' => "Guru {$n}",
            'nip' => $nip, 'gender' => 'perempuan', 'status' => 'active',
        ]);

        TeacherClassAssignment::create([
            'teacher_id' => $teacher->id, 'school_id' => $school->id,
            'academic_year_id' => $year->id, 'rombel_id' => $rombel->id, 'is_active' => true,
        ]);

        return $user;
    }

    protected function makeHeadmaster(School $school): User
    {
        $n = ++$this->seq;
        $nip = sprintf('1980%014d', $n);

        $user = User::create(['role' => 'headmaster', 'username' => $nip, 'password' => 'secret']);

        Headmaster::create([
            'user_id' => $user->id, 'school_id' => $school->id, 'fullname' => "Kepsek {$n}",
            'nip' => $nip, 'gender' => 'laki-laki', 'status' => 'active',
        ]);

        return $user;
    }

    protected function makeSuperAdmin(): User
    {
        return User::create(['role' => 'super_admin', 'username' => 'sa' . ++$this->seq, 'password' => 'secret']);
    }

    /** Student + active assignment di rombel tsb. */
    protected function makeStudent(School $school, AcademicYear $year, Rombel $rombel, int $poin): Student
    {
        $n = ++$this->seq;
        $nisn = sprintf('%010d', 5000000000 + $n);

        $user = User::create(['role' => 'student', 'username' => $nisn, 'password' => $nisn]);

        $student = Student::create([
            'user_id' => $user->id, 'school_id' => $school->id, 'fullname' => "Siswa {$n}",
            'nisn' => $nisn, 'gender' => 'laki-laki', 'poin' => $poin, 'status' => 'active',
        ]);

        StudentClassAssignment::create([
            'student_id' => $student->id, 'school_id' => $school->id,
            'rombel_id' => $rombel->id, 'academic_year_id' => $year->id, 'status' => 'active',
        ]);

        return $student;
    }

    protected function authHeader(User $user): array
    {
        return ['Authorization' => 'Bearer ' . auth('api')->login($user)];
    }
}
