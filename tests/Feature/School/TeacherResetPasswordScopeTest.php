<?php

namespace Tests\Feature\School;

use App\Models\Headmaster;
use App\Models\School;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Regresi bug: POST /teachers/{id}/reset-password mengembalikan 404 untuk
// Teacher di sekolah lain, padahal API Contract v1.2 §10 eksplisit minta 403
// ("403 bila target di luar scope actor (cross-school untuk Headmaster→
// Teacher); 404 bila target tidak ditemukan"). Root cause: resetPassword()
// reuse show() yang filter school_id SEBELUM find() -> "tidak ada" dan "ada
// tapi beda sekolah" jadi tidak bisa dibedakan, keduanya jatuh ke 404.
// Fix: TeacherService::findTeacherOrFail() (fetch global) + cek school
// terpisah. show() sendiri SENGAJA tidak diubah (GET tetap 404 untuk
// keduanya, sesuai §26).
//
// Setup pakai Model::create() berantai, konsisten dengan
// tests/Feature/Student/StudentLifecycleTest.php.
//
// STATUS EKSEKUSI: dijalankan di PHP + PostgreSQL; hasil terkini lihat docs/testing/BASELINE-STAGE-0C.md

class TeacherResetPasswordScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function createHeadmaster(School $school, string $nip): User
    {
        $user = User::create([
            'role'     => 'headmaster',
            'username' => $nip,
            'password' => 'secret',
        ]);

        Headmaster::create([
            'user_id'   => $user->id,
            'school_id' => $school->id,
            'fullname'  => 'Kepala Sekolah ' . $nip,
            'nip'       => $nip,
            'gender'    => 'laki-laki',
            'status'    => 'active',
        ]);

        return $user;
    }

    protected function createTeacher(School $school, string $nip): Teacher
    {
        $user = User::create([
            'role'     => 'teacher',
            'username' => $nip,
            'password' => 'secret',
        ]);

        return Teacher::create([
            'user_id'   => $user->id,
            'school_id' => $school->id,
            'fullname'  => 'Guru ' . $nip,
            'nip'       => $nip,
            'gender'    => 'perempuan',
            'status'    => 'active',
        ]);
    }

    /** @test */
    public function headmaster_reset_password_teacher_sekolah_sendiri_berhasil_200(): void
    {
        $school = School::create(['name' => 'SD Satu']);
        $hmUser = $this->createHeadmaster($school, '1001');
        $teacher = $this->createTeacher($school, '2001');

        $token = auth('api')->login($hmUser);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/teachers/{$teacher->id}/reset-password", [
                'new_password' => 'passwordbaru123',
            ]);

        $response->assertStatus(200);
    }

    /**
     * Ini kasus yang tadinya bug: Teacher ADA di database (bukan id ngasal),
     * tapi di sekolah LAIN dari Headmaster yang me-reset. Harus 403, bukan 404.
     *
     * @test
     */
    public function headmaster_reset_password_teacher_sekolah_lain_ditolak_403_bukan_404(): void
    {
        $schoolA = School::create(['name' => 'SD Satu']);
        $schoolB = School::create(['name' => 'SD Dua']);

        $hmUserA = $this->createHeadmaster($schoolA, '1002');
        $teacherB = $this->createTeacher($schoolB, '2002');

        $token = auth('api')->login($hmUserA);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/teachers/{$teacherB->id}/reset-password", [
                'new_password' => 'passwordbaru123',
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function headmaster_reset_password_teacher_id_tidak_ada_tetap_404(): void
    {
        $school = School::create(['name' => 'SD Satu']);
        $hmUser = $this->createHeadmaster($school, '1003');

        $token = auth('api')->login($hmUser);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/teachers/999999/reset-password', [
                'new_password' => 'passwordbaru123',
            ]);

        $response->assertStatus(404);
    }
}
