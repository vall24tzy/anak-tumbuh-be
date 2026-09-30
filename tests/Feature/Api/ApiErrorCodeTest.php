<?php

namespace Tests\Feature\Api;

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

// Stage 0-A — F-47 (405/429 error code) + F-45 (error.code spesifik untuk
// semantic rule, validasi biasa tetap validation_failed).
// Matrix v3 §F-45/§F-47; API Contract v1.2 §9, §17, §19, §21.

class ApiErrorCodeTest extends TestCase
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

        $assignment = StudentClassAssignment::create([
            'student_id'       => $student->id,
            'school_id'        => $school->id,
            'rombel_id'        => $rombel->id,
            'academic_year_id' => $academicYear->id,
            'status'           => 'active',
        ]);

        return [$teacherUser, $student, $assignment];
    }

    protected function assertErrorEnvelope($response, int $status, string $code): void
    {
        $response->assertStatus($status);
        $response->assertJsonPath('error.code', $code);
        $response->assertJsonStructure(['error' => ['code', 'message', 'details']]);
        $this->assertIsArray($response->json('error.details'));
    }

    // ---------- F-47 ----------

    /** @test */
    public function method_salah_dikembalikan_405_method_not_allowed(): void
    {
        $response = $this->getJson('/api/v1/auth/login/staff');

        $this->assertErrorEnvelope($response, 405, 'method_not_allowed');
    }

    /** @test */
    public function throttle_terlampaui_dikembalikan_429_rate_limit_exceeded(): void
    {
        $payload = ['username' => 'tidak-ada', 'password' => 'salah'];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login/staff', $payload)->assertStatus(401);
        }

        $response = $this->postJson('/api/v1/auth/login/staff', $payload);

        $this->assertErrorEnvelope($response, 429, 'rate_limit_exceeded');
    }

    /** @test */
    public function status_lain_di_luar_pemetaan_tetap_internal_error(): void
    {
        $responder = new class {
            use \App\Traits\ApiResponseTraits;
        };

        $response = $responder->errorResponse('x', 418);

        $this->assertSame('internal_error', $response->getData(true)['error']['code']);
    }

    // ---------- F-45 ----------

    /** @test */
    public function patch_student_field_terlarang_dikembalikan_protected_field(): void
    {
        [$teacherUser, $student] = $this->setUpScenario();
        $token = auth('api')->login($teacherUser);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/students/{$student->id}", ['nisn' => '9999999999']);

        $this->assertErrorEnvelope($response, 422, 'protected_field');
        $response->assertJsonPath('error.details.0.field', 'nisn');
        $response->assertJsonPath('error.details.0.reason', 'protected_field');
        $this->assertDatabaseHas('students', ['id' => $student->id, 'nisn' => '0051234567']);
    }

    /** @test */
    public function protected_field_diprioritaskan_sebelum_validasi_lain(): void
    {
        [$teacherUser, $student] = $this->setUpScenario();
        $token = auth('api')->login($teacherUser);

        // name > 150 karakter (rule gagal) + field terlarang -> protected_field menang
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/students/{$student->id}", [
                'name'      => str_repeat('a', 151),
                'school_id' => 999,
            ]);

        $this->assertErrorEnvelope($response, 422, 'protected_field');
        $response->assertJsonPath('error.details.0.field', 'school_id');
        $this->assertCount(1, $response->json('error.details'));
    }

    /** @test */
    public function patch_student_validasi_biasa_tetap_validation_failed(): void
    {
        [$teacherUser, $student] = $this->setUpScenario();
        $token = auth('api')->login($teacherUser);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/students/{$student->id}", ['name' => str_repeat('a', 151)]);

        $this->assertErrorEnvelope($response, 422, 'validation_failed');
        $response->assertJsonPath('error.details.0.field', 'name');
    }

    /** @test */
    public function patch_student_class_assignment_field_lain_dikembalikan_field_not_accepted(): void
    {
        [$teacherUser, , $assignment] = $this->setUpScenario();
        $token = auth('api')->login($teacherUser);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/student-class-assignments/{$assignment->id}", [
                'status'    => 'ended',
                'rombel_id' => 123,
            ]);

        $this->assertErrorEnvelope($response, 422, 'field_not_accepted');
        $response->assertJsonPath('error.details.0.field', 'rombel_id');
        $response->assertJsonPath('error.details.0.reason', 'field_not_accepted');
        $this->assertDatabaseHas('student_class_assignments', ['id' => $assignment->id, 'status' => 'active']);
    }

    /** @test */
    public function patch_student_class_assignment_status_invalid_tetap_validation_failed(): void
    {
        [$teacherUser, , $assignment] = $this->setUpScenario();
        $token = auth('api')->login($teacherUser);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/student-class-assignments/{$assignment->id}", ['status' => 'active']);

        $this->assertErrorEnvelope($response, 422, 'validation_failed');
        $response->assertJsonPath('error.details.0.field', 'status');
    }
}