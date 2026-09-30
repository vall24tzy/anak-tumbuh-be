<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolFixtures;
use Tests\TestCase;

// STAGE 0-C - menutup sebagian TEST GAP F-22 (Master Audit Matrix v3):
// login staff, login Student via password, logout - happy path + 401/422.
// Dasar SoT: API Contract v1.2 §10; Authorization Matrix #52 (login Student
// username+password, SA/HM/Teacher DENY) dan #53 (logout: 4 role ALLOW).
//
// SENGAJA TIDAK ADA di file ini (bergantung pada finding yang masih OPEN,
// harus ditulis bersamaan dengan fix-nya, bukan sekarang):
//   - skenario F-01 (login/staff menerima akun Student) -> ditulis bersama fix F-01
//   - skenario F-23 (rate limit login)                  -> ditulis bersama fix F-23
// Gate inactive/graduated (F-14/F-24) sudah dicakup SessionValidityTest.
//
// CATATAN RATE LIMIT: ketiga route login memakai throttle:5,1 dan (tanpa nama
// limiter) berbagi satu kunci per IP. Batas 5 request login per test - jangan
// tambah request login di test tanpa menghitung ulang, kalau tidak jadi 429.
//
// STATUS EKSEKUSI: dijalankan di PHP + PostgreSQL; hasil terkini lihat docs/testing/BASELINE-STAGE-0C.md

class AuthLoginLogoutTest extends TestCase
{
    use RefreshDatabase, CreatesSchoolFixtures;

    protected function loginStaff(array $body)
    {
        return $this->postJson('/api/v1/auth/login/staff', $body);
    }

    protected function loginStudent(array $body)
    {
        return $this->postJson('/api/v1/auth/login/student/password', $body);
    }

    // ---------- login staff ----------

    /** @test */
    public function login_staff_super_admin_berhasil_dan_token_bisa_dipakai(): void
    {
        $sa = $this->fxSuperAdmin('sa_login');

        $response = $this->loginStaff(['username' => 'sa_login', 'password' => 'secret']);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['access_token', 'token_type', 'user' => ['id', 'role', 'name']]])
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.id', $sa->id)
            ->assertJsonPath('data.user.role', 'super_admin')
            ->assertJsonMissingPath('data.user.password');

        $this->apiGet('/api/v1/auth/me', $response->json('data.access_token'))
            ->assertStatus(200)
            ->assertJsonPath('data.id', $sa->id);
    }

    /** @test */
    public function login_staff_headmaster_dan_teacher_aktif_berhasil(): void
    {
        $school = $this->fxSchool();
        $hm = $this->fxHeadmaster($school, '1001');
        $teacher = $this->fxTeacher($school, '2001');

        $this->loginStaff(['username' => '1001', 'password' => 'secret'])
            ->assertStatus(200)
            ->assertJsonPath('data.user.id', $hm->id)
            ->assertJsonPath('data.user.role', 'headmaster')
            ->assertJsonPath('data.user.name', 'Kepsek 1001');

        $this->loginStaff(['username' => '2001', 'password' => 'secret'])
            ->assertStatus(200)
            ->assertJsonPath('data.user.id', $teacher->id)
            ->assertJsonPath('data.user.role', 'teacher')
            ->assertJsonPath('data.user.name', 'Guru 2001');
    }

    /** @test */
    public function login_staff_password_salah_dan_username_tidak_ada_401_dengan_pesan_sama(): void
    {
        $this->fxSuperAdmin('sa_login');

        $wrong = $this->loginStaff(['username' => 'sa_login', 'password' => 'salah-total']);
        $unknown = $this->loginStaff(['username' => 'tidak-ada', 'password' => 'secret']);

        $wrong->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthorized')
            ->assertJsonPath('error.message', 'Username atau password salah')
            ->assertJsonMissingPath('data.access_token');

        // Pesan identik -> tidak membocorkan apakah username terdaftar.
        $unknown->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthorized')
            ->assertJsonPath('error.message', 'Username atau password salah');
    }

    /** @test */
    public function login_staff_field_wajib_kosong_422(): void
    {
        $this->loginStaff(['password' => 'secret'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->loginStaff(['username' => 'sa_login'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    // ---------- login Student via password (Matrix #52) ----------

    /** @test */
    public function login_student_password_berhasil_dan_token_bisa_dipakai(): void
    {
        $student = $this->fxStudent($this->fxSchool(), '0051110001');

        $response = $this->loginStudent(['nisn' => '0051110001', 'password' => '0051110001']);

        $response->assertStatus(200)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.id', $student->id)
            ->assertJsonPath('data.user.role', 'student')
            ->assertJsonPath('data.user.name', 'Siswa 0051110001');

        $this->apiGet('/api/v1/auth/me', $response->json('data.access_token'))
            ->assertStatus(200)
            ->assertJsonPath('data.nisn', '0051110001');
    }

    /** @test */
    public function login_student_password_salah_dan_nisn_tidak_ada_401(): void
    {
        $this->fxStudent($this->fxSchool(), '0051110001');

        $this->loginStudent(['nisn' => '0051110001', 'password' => 'salah-total'])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthorized')
            ->assertJsonPath('error.message', 'NISN atau password salah');

        $this->loginStudent(['nisn' => '9999999999', 'password' => '9999999999'])
            ->assertStatus(401)
            ->assertJsonPath('error.message', 'NISN atau password salah');
    }

    /** @test */
    public function login_student_password_tidak_menerima_akun_staff_401(): void
    {
        // Matrix #52: SA/HM/Teacher DENY di jalur login Student.
        $this->fxHeadmaster($this->fxSchool(), '1001');

        $this->loginStudent(['nisn' => '1001', 'password' => 'secret'])
            ->assertStatus(401)
            ->assertJsonPath('error.message', 'NISN atau password salah')
            ->assertJsonMissingPath('data.access_token');
    }

    /** @test */
    public function login_student_password_field_wajib_kosong_422(): void
    {
        $this->loginStudent(['password' => '0051110001'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->loginStudent(['nisn' => '0051110001'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    // ---------- logout (Matrix #53) ----------

    /** @test */
    public function logout_berhasil_untuk_keempat_role_dan_token_langsung_tidak_berlaku(): void
    {
        $school = $this->fxSchool();
        $users = [
            $this->fxSuperAdmin('sa_logout'),
            $this->fxHeadmaster($school, '1001'),
            $this->fxTeacher($school, '2001'),
            $this->fxStudent($school, '0051110001'),
        ];

        foreach ($users as $user) {
            $token = $this->fxToken($user);

            $this->apiGet('/api/v1/auth/me', $token)->assertStatus(200);
            $this->apiPost('/api/v1/auth/logout', $token)->assertStatus(200);
            $this->apiGet('/api/v1/auth/me', $token)
                ->assertStatus(401)
                ->assertJsonPath('error.code', 'unauthorized');
        }
    }

    /** @test */
    public function logout_kedua_kali_dengan_token_yang_sama_ditolak_401(): void
    {
        $token = $this->fxToken($this->fxSuperAdmin('sa_logout'));

        $this->apiPost('/api/v1/auth/logout', $token)->assertStatus(200);
        $this->apiPost('/api/v1/auth/logout', $token)->assertStatus(401);
    }

    /** @test */
    public function logout_tanpa_token_dan_dengan_token_invalid_401(): void
    {
        $this->apiPost('/api/v1/auth/logout')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthorized');

        $this->apiPost('/api/v1/auth/logout', 'bukan.token.valid')->assertStatus(401);
    }
}
