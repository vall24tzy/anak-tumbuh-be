<?php

namespace Tests\Feature\Student;

use App\Models\AcademicYear;
use App\Models\Rombel;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentClassAssignment;
use App\Models\Teacher;
use App\Models\TeacherClassAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// STUDENT-001 NOTE-06 (24 Sep 2026, lihat docs/db-gap-analysis.md NOTE-06):
// body PATCH /api/v1/students/{id} pakai key "name" (bukan "fullname").
// "fullname" pada PATCH wajib ditolak 422 protected_field.

class StudentUpdateNameFieldTest extends TestCase
{
    use RefreshDatabase;

    protected function setUpScenario(): array
    {
        $school = School::create(['name' => 'SD Contoh']);

        $academicYear = AcademicYear::create([
            'school_id'  => $school->id,
            'name'       => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date'   => '2027-06-30',
            'is_active'  => true,
        ]);

        $rombel = Rombel::create([
            'school_id'        => $school->id,
            'academic_year_id' => $academicYear->id,
            'name'             => 'VII-A',
            'grade_level'      => 7,
        ]);

        $teacherUser = User::create([
            'role'     => 'teacher',
            'username' => '199001012020121001',
            'password' => 'secret',
        ]);

        $teacher = Teacher::create([
            'user_id'   => $teacherUser->id,
            'school_id' => $school->id,
            'fullname'  => 'Bu Guru',
            'nip'       => '199001012020121001',
            'gender'    => 'perempuan',
            'status'    => 'active',
        ]);

        TeacherClassAssignment::create([
            'teacher_id'       => $teacher->id,
            'school_id'        => $school->id,
            'academic_year_id' => $academicYear->id,
            'rombel_id'        => $rombel->id,
            'is_active'        => true,
        ]);

        $studentUser = User::create([
            'role'     => 'student',
            'username' => '0051234567',
            'password' => '0051234567',
        ]);

        $student = Student::create([
            'user_id'   => $studentUser->id,
            'school_id' => $school->id,
            'fullname'  => 'Nama Lama',
            'nisn'      => '0051234567',
            'gender'    => 'laki-laki',
            'poin'      => 0,
            'status'    => 'active',
        ]);

        StudentClassAssignment::create([
            'student_id'       => $student->id,
            'school_id'        => $school->id,
            'rombel_id'        => $rombel->id,
            'academic_year_id' => $academicYear->id,
            'status'           => 'active',
        ]);

        return [$teacherUser, $student];
    }

    /** @test */
    public function patch_name_oleh_teacher_pemilik_own_active_rombel_mengubah_kolom_fullname(): void
    {
        [$teacherUser, $student] = $this->setUpScenario();
        $token = auth('api')->login($teacherUser);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/students/{$student->id}", [
                'name' => 'Nama Baru',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('students', [
            'id'       => $student->id,
            'fullname' => 'Nama Baru',
        ]);
    }

    /** @test */
    public function patch_fullname_key_lama_ditolak_422_protected_field(): void
    {
        [$teacherUser, $student] = $this->setUpScenario();
        $token = auth('api')->login($teacherUser);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/students/{$student->id}", [
                'fullname' => 'Nama Baru',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'validation_failed');
        $response->assertJsonPath('error.details.0.field', 'fullname');
        $response->assertJsonPath('error.details.0.message', 'protected_field');

        $this->assertDatabaseMissing('students', [
            'id'       => $student->id,
            'fullname' => 'Nama Baru',
        ]);
    }
}
