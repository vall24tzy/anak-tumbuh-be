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

/**
 * Fixture bersama untuk test gap Stage 0-C (Auth/Password/Show-scope/Dashboard access).
 * Semua method berawalan `fx` / `api` supaya tidak bentrok dengan method bawaan
 * TestCase (get/post/dst). Setup pakai Model::create() berantai, konsisten dengan
 * test lain di repo ini (tidak ada factory). Hanya untuk test BARU Stage 0-C;
 * test lama tidak diubah.
 *
 * Password awal: staff = 'secret', Student = NISN (sesuai Matrix #44-#46).
 * Fullname: 'Kepsek <nip>' / 'Guru <nip>' / 'Siswa <nisn>'.
 */
trait CreatesSchoolFixtures
{
    protected function fxSchool(string $name = 'SD Satu'): School
    {
        return School::create(['name' => $name]);
    }

    protected function fxSuperAdmin(string $username = 'sa_fx'): User
    {
        return User::create(['role' => 'super_admin', 'username' => $username, 'password' => 'secret']);
    }

    /** Headmaster aktif. Profil: $user->headmaster. */
    protected function fxHeadmaster(School $school, string $nip): User
    {
        $user = User::create(['role' => 'headmaster', 'username' => $nip, 'password' => 'secret']);

        Headmaster::create([
            'user_id' => $user->id, 'school_id' => $school->id, 'fullname' => 'Kepsek ' . $nip,
            'nip' => $nip, 'gender' => 'laki-laki', 'status' => 'active',
        ]);

        return $user;
    }

    /** Teacher aktif. Profil: $user->teacher. */
    protected function fxTeacher(School $school, string $nip): User
    {
        $user = User::create(['role' => 'teacher', 'username' => $nip, 'password' => 'secret']);

        Teacher::create([
            'user_id' => $user->id, 'school_id' => $school->id, 'fullname' => 'Guru ' . $nip,
            'nip' => $nip, 'gender' => 'perempuan', 'status' => 'active',
        ]);

        return $user;
    }

    /** Student aktif (username = password awal = NISN). Profil: $user->student. */
    protected function fxStudent(School $school, string $nisn): User
    {
        $user = User::create(['role' => 'student', 'username' => $nisn, 'password' => $nisn]);

        Student::create([
            'user_id' => $user->id, 'school_id' => $school->id, 'fullname' => 'Siswa ' . $nisn,
            'nisn' => $nisn, 'gender' => 'laki-laki', 'poin' => 0, 'status' => 'active',
        ]);

        return $user;
    }

    protected function fxAcademicYear(School $school, string $name = '2026/2027'): AcademicYear
    {
        return AcademicYear::create([
            'school_id' => $school->id, 'name' => $name,
            'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true,
        ]);
    }

    protected function fxRombel(School $school, AcademicYear $year, string $name): Rombel
    {
        return Rombel::create([
            'school_id' => $school->id, 'academic_year_id' => $year->id, 'name' => $name, 'grade_level' => 7,
        ]);
    }

    protected function fxTeacherAssignment(Teacher $teacher, Rombel $rombel): TeacherClassAssignment
    {
        return TeacherClassAssignment::create([
            'teacher_id' => $teacher->id, 'school_id' => $rombel->school_id,
            'academic_year_id' => $rombel->academic_year_id, 'rombel_id' => $rombel->id, 'is_active' => true,
        ]);
    }

    protected function fxStudentAssignment(Student $student, Rombel $rombel): StudentClassAssignment
    {
        return StudentClassAssignment::create([
            'student_id' => $student->id, 'school_id' => $rombel->school_id,
            'rombel_id' => $rombel->id, 'academic_year_id' => $rombel->academic_year_id, 'status' => 'active',
        ]);
    }

    protected function fxToken(User $user): string
    {
        return (string) auth('api')->login($user);
    }

    /**
     * Header dikirim PER PANGGILAN (bukan withHeader) supaya tidak "menempel"
     * ke request berikutnya di test yang sama - penting untuk kasus tanpa token.
     */
    protected function apiGet(string $uri, ?string $token = null)
    {
        return $this->getJson($uri, $token ? ['Authorization' => "Bearer {$token}"] : []);
    }

    protected function apiPost(string $uri, ?string $token = null, array $body = [])
    {
        return $this->postJson($uri, $body, $token ? ['Authorization' => "Bearer {$token}"] : []);
    }
}
