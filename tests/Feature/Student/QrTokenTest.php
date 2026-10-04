<?php

namespace Tests\Feature\Student;

use App\Models\AcademicYear;
use App\Models\QrToken;
use App\Models\Rombel;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentClassAssignment;
use App\Models\Teacher;
use App\Models\TeacherClassAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

// QR-001 — API Contract v1.2 §10/§17, DB Design v1.5 §5.15, Matrix #51.
// Setup pakai Model::create() berantai, konsisten dengan
// tests/Feature/Student/StudentLifecycleTest.php.
//
// STATUS EKSEKUSI: dijalankan di PHP + PostgreSQL; hasil terkini lihat docs/testing/BASELINE-STAGE-0C.md

class QrTokenTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Student} [$teacherUser, $student]
     */
    protected function setUpScenario(string $nip = '199001012020121001', string $nisn = '0051234567'): array
    {
        $school = School::create(['name' => 'SD Contoh ' . $nip]);

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
            'username' => $nip,
            'password' => 'secret',
        ]);

        $teacher = Teacher::create([
            'user_id'   => $teacherUser->id,
            'school_id' => $school->id,
            'fullname'  => 'Bu Guru',
            'nip'       => $nip,
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
            'username' => $nisn,
            'password' => $nisn,
        ]);

        $student = Student::create([
            'user_id'   => $studentUser->id,
            'school_id' => $school->id,
            'fullname'  => 'Siswa Contoh',
            'nisn'      => $nisn,
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

        QrToken::create([
            'student_id' => $student->id,
            'token_hash' => Hash::make((string) Str::uuid()),
        ]);

        return [$teacherUser, $student];
    }

    /** @test */
    public function teacher_regenerate_qr_pada_own_active_rombel_mengembalikan_qr_payload(): void
    {
        [$teacherUser, $student] = $this->setUpScenario();
        $token = auth('api')->login($teacherUser);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/qr-tokens/{$student->id}/regenerate");

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['qr_payload']]);

        $payload = $response->json('data.qr_payload');
        $this->assertStringContainsString('.', $payload);
    }

    /** @test */
    public function login_qr_dengan_payload_hasil_regenerate_berhasil_200_dan_dapat_access_token(): void
    {
        [$teacherUser, $student] = $this->setUpScenario();
        $token = auth('api')->login($teacherUser);

        $regenerate = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/qr-tokens/{$student->id}/regenerate");

        $qrPayload = $regenerate->json('data.qr_payload');

        $response = $this->postJson('/api/v1/auth/login/student/qr', [
            'qr_payload' => $qrPayload,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['access_token', 'token_type', 'user']]);
    }

    /** @test */
    public function login_qr_setelah_regenerate_ulang_token_lama_tidak_valid_lagi(): void
    {
        [$teacherUser, $student] = $this->setUpScenario();
        $token = auth('api')->login($teacherUser);

        $first = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/qr-tokens/{$student->id}/regenerate");
        $oldPayload = $first->json('data.qr_payload');

        // Regenerate lagi -> token lama harus invalid (1 baris per Student di-update).
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/qr-tokens/{$student->id}/regenerate");

        $response = $this->postJson('/api/v1/auth/login/student/qr', [
            'qr_payload' => $oldPayload,
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function login_qr_dengan_payload_dipalsukan_ditolak_401(): void
    {
        $response = $this->postJson('/api/v1/auth/login/student/qr', [
            'qr_payload' => base64_encode(json_encode(['student_id' => 1, 'token' => 'ngasal'])) . '.signature-palsu',
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function login_qr_untuk_student_inactive_ditolak_403(): void
    {
        [$teacherUser, $student] = $this->setUpScenario();
        $token = auth('api')->login($teacherUser);

        $regenerate = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/qr-tokens/{$student->id}/regenerate");
        $qrPayload = $regenerate->json('data.qr_payload');

        $student->update(['status' => 'inactive']);

        $response = $this->postJson('/api/v1/auth/login/student/qr', [
            'qr_payload' => $qrPayload,
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function teacher_regenerate_qr_student_di_luar_own_active_rombel_ditolak_403(): void
    {
        [, $studentA] = $this->setUpScenario('199001012020121001', '0051234567');
        [$teacherUserB] = $this->setUpScenario('199001012020121002', '0051234568');

        $token = auth('api')->login($teacherUserB);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/qr-tokens/{$studentA->id}/regenerate");

        $response->assertStatus(403);
    }

    /** @test */
    public function regenerate_qr_student_id_tidak_ada_dikembalikan_404(): void
    {
        [$teacherUser] = $this->setUpScenario();
        $token = auth('api')->login($teacherUser);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/qr-tokens/999999/regenerate');

        $response->assertStatus(404);
    }

    /** @test */
    public function student_sendiri_tidak_bisa_regenerate_qr_403(): void
    {
        [, $student] = $this->setUpScenario();

        $studentUser = $student->user;
        $token = auth('api')->login($studentUser);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/qr-tokens/{$student->id}/regenerate");

        $response->assertStatus(403);
    }
}
