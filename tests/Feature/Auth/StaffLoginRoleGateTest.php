<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolFixtures;
use Tests\TestCase;

// F-01 - POST /auth/login/staff tidak boleh menerima akun Student.
//
// Bug: loginStaff mencari User hanya via username (tanpa filter role). Akun
// role 'student' (username = NISN) bisa login lewat jalur staff, dan Student
// inactive/graduated malah mendapat 403 "Akun tidak aktif" (membocorkan bahwa
// akun itu ada). SoT: Matrix v3.1 F-01 (AC), DB Design R7, Dokumen v4.0 §4.1,
// API Contract §10; Authorization Matrix #52 (jalur login Student terpisah).
//
// Yang dikunci:
//   - Student (aktif / inactive / graduated) via login staff -> 401 "Username
//     atau password salah", TANPA token; tidak dapat dibedakan dari username
//     yang tidak ada (tidak membocorkan status/keberadaan akun)
//   - Super Admin / Headmaster / Teacher aktif tetap 200
//   - Headmaster / Teacher inactive tetap 403 (gate Stage 0-B tidak rusak)
//   - Student tetap bisa login lewat jalur Student
//
// CATATAN RATE LIMIT: ketiga route login berbagi throttle:5,1 per IP; setiap
// test di file ini memakai maksimal 5 request login.
//
// STATUS EKSEKUSI: dijalankan di PHP + PostgreSQL; hasil terkini lihat docs/testing/BASELINE-STAGE-0C.md

class StaffLoginRoleGateTest extends TestCase
{
    use RefreshDatabase, CreatesSchoolFixtures;

    private const NISN = '0051234567';

    protected function loginStaff(array $body)
    {
        return $this->postJson('/api/v1/auth/login/staff', $body);
    }

    protected function assertCredentialRejected($response): void
    {
        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthorized')
            ->assertJsonPath('error.message', 'Username atau password salah')
            ->assertJsonMissingPath('data.access_token')
            ->assertJsonMissingPath('data');
    }

    /** @test */
    public function student_aktif_via_login_staff_ditolak_401_tanpa_token(): void
    {
        $school = $this->fxSchool();
        $this->fxStudent($school, self::NISN);

        $this->assertCredentialRejected(
            $this->loginStaff(['username' => self::NISN, 'password' => self::NISN])
        );
    }

    /**
     * @test
     * @dataProvider nonActiveStudentStatuses
     */
    public function student_inactive_atau_graduated_via_login_staff_401_bukan_403(string $status): void
    {
        $school = $this->fxSchool();
        $student = $this->fxStudent($school, self::NISN);
        $student->student->update(['status' => $status]);

        $this->assertCredentialRejected(
            $this->loginStaff(['username' => self::NISN, 'password' => self::NISN])
        );
    }

    public static function nonActiveStudentStatuses(): array
    {
        return [
            'inactive'  => ['inactive'],
            'graduated' => ['graduated'],
        ];
    }

    /** @test */
    public function respons_student_tidak_dapat_dibedakan_dari_username_yang_tidak_ada(): void
    {
        $school = $this->fxSchool();
        $this->fxStudent($school, self::NISN);

        $studentResponse = $this->loginStaff(['username' => self::NISN, 'password' => self::NISN]);
        $missingResponse = $this->loginStaff(['username' => '9999999999', 'password' => 'apa-saja']);

        $this->assertSame($missingResponse->getStatusCode(), $studentResponse->getStatusCode());
        $this->assertSame($missingResponse->json('error.code'), $studentResponse->json('error.code'));
        $this->assertSame($missingResponse->json('error.message'), $studentResponse->json('error.message'));
    }

    /** @test */
    public function super_admin_headmaster_dan_teacher_aktif_tetap_berhasil_login_staff(): void
    {
        $school = $this->fxSchool();
        $this->fxSuperAdmin('sa_f01');
        $this->fxHeadmaster($school, '1001');
        $this->fxTeacher($school, '2001');

        foreach ([['sa_f01', 'super_admin'], ['1001', 'headmaster'], ['2001', 'teacher']] as [$username, $role]) {
            $response = $this->loginStaff(['username' => $username, 'password' => 'secret']);

            $response->assertStatus(200)->assertJsonPath('data.user.role', $role);
            $this->assertNotEmpty($response->json('data.access_token'));
        }
    }

    /** @test */
    public function headmaster_dan_teacher_inactive_tetap_403_setelah_filter_role(): void
    {
        $school = $this->fxSchool();
        $hm = $this->fxHeadmaster($school, '1001');
        $teacher = $this->fxTeacher($school, '2001');
        $hm->headmaster->update(['status' => 'inactive']);
        $teacher->teacher->update(['status' => 'inactive']);

        foreach (['1001', '2001'] as $username) {
            $this->loginStaff(['username' => $username, 'password' => 'secret'])
                ->assertStatus(403)
                ->assertJsonPath('error.code', 'forbidden')
                ->assertJsonMissingPath('data.access_token');
        }
    }

    /** @test */
    public function student_aktif_tetap_bisa_login_lewat_jalur_student(): void
    {
        $school = $this->fxSchool();
        $student = $this->fxStudent($school, self::NISN);

        $response = $this->postJson('/api/v1/auth/login/student/password', [
            'nisn'     => self::NISN,
            'password' => self::NISN,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.user.id', $student->id)
            ->assertJsonPath('data.user.role', 'student');
        $this->assertNotEmpty($response->json('data.access_token'));
    }
}
