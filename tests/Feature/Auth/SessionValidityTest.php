<?php

namespace Tests\Feature\Auth;

use App\Models\AcademicYear;
use App\Models\Headmaster;
use App\Models\Rombel;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentClassAssignment;
use App\Models\Teacher;
use App\Models\TeacherClassAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Facades\JWTFactory;

// STAGE 0-B "Grup S" - F-14 (Headmaster/Teacher inactive), F-24 (Student
// inactive/graduated), F-29 (change/reset password mencabut token lama target).
// Mekanisme: middleware `jwt` = App\Http\Middleware\AuthenticateActiveSession
// (claim `pwd` sidik jari password + gate status profil), tanpa perubahan skema.
//
// Setup pakai Model::create() berantai, konsisten dengan test lain di repo ini.
//
// STATUS EKSEKUSI: dijalankan di PHP + PostgreSQL: 22 passed, 90 assertions; full suite baseline Stage 0-C 142 passed, 531 assertions
// (lihat docs/testing/BASELINE-STAGE-0C.md). Ulangi: php artisan test --filter=SessionValidityTest

class SessionValidityTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_PASSWORD = 'PasswordBaru123';

    // ---------- helper ----------

    protected function makeSchool(string $name = 'SD Satu'): School
    {
        return School::create(['name' => $name]);
    }

    protected function makeSuperAdmin(string $username = 'sa_test'): User
    {
        return User::create(['role' => 'super_admin', 'username' => $username, 'password' => 'secret']);
    }

    protected function makeHeadmaster(School $school, string $nip, string $status = 'active'): User
    {
        $user = User::create(['role' => 'headmaster', 'username' => $nip, 'password' => 'secret']);

        Headmaster::create([
            'user_id' => $user->id, 'school_id' => $school->id, 'fullname' => 'Kepsek ' . $nip,
            'nip' => $nip, 'gender' => 'laki-laki', 'status' => $status,
        ]);

        return $user;
    }

    protected function makeTeacher(School $school, string $nip, string $status = 'active'): array
    {
        $user = User::create(['role' => 'teacher', 'username' => $nip, 'password' => 'secret']);

        $teacher = Teacher::create([
            'user_id' => $user->id, 'school_id' => $school->id, 'fullname' => 'Guru ' . $nip,
            'nip' => $nip, 'gender' => 'perempuan', 'status' => $status,
        ]);

        return [$user, $teacher];
    }

    protected function makeStudent(School $school, string $nisn, string $status = 'active'): array
    {
        $user = User::create(['role' => 'student', 'username' => $nisn, 'password' => $nisn]);

        $student = Student::create([
            'user_id' => $user->id, 'school_id' => $school->id, 'fullname' => 'Siswa ' . $nisn,
            'nisn' => $nisn, 'gender' => 'laki-laki', 'poin' => 0, 'status' => $status,
        ]);

        return [$user, $student];
    }

    protected function bearer(User $user): string
    {
        return (string) auth('api')->login($user);
    }

    protected function getAs(string $uri, string $token)
    {
        return $this->withHeader('Authorization', "Bearer {$token}")->getJson($uri);
    }

    protected function postAs(string $uri, string $token, array $body = [])
    {
        return $this->withHeader('Authorization', "Bearer {$token}")->postJson($uri, $body);
    }

    protected function assertInactiveRejected($response): void
    {
        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'forbidden');
        $response->assertJsonPath('error.message', 'Akun tidak aktif');
    }

    protected function assertSessionRejected($response): void
    {
        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'unauthorized');
    }

    /** Teacher dengan active rombel + 1 Student di rombel itu (untuk reset password Student). */
    protected function makeTeacherWithStudent(): array
    {
        $school = $this->makeSchool('SD Rombel');
        $year = AcademicYear::create([
            'school_id' => $school->id, 'name' => '2026/2027',
            'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true,
        ]);
        $rombel = Rombel::create([
            'school_id' => $school->id, 'academic_year_id' => $year->id, 'name' => 'VII-A', 'grade_level' => 7,
        ]);

        [$teacherUser, $teacher] = $this->makeTeacher($school, '3001');
        TeacherClassAssignment::create([
            'teacher_id' => $teacher->id, 'school_id' => $school->id,
            'academic_year_id' => $year->id, 'rombel_id' => $rombel->id, 'is_active' => true,
        ]);

        [$studentUser, $student] = $this->makeStudent($school, '0051230001');
        StudentClassAssignment::create([
            'student_id' => $student->id, 'school_id' => $school->id,
            'rombel_id' => $rombel->id, 'academic_year_id' => $year->id, 'status' => 'active',
        ]);

        return [$teacherUser, $studentUser, $student];
    }

    // ---------- F-14: login gate ----------

    /** @test */
    public function login_staff_headmaster_aktif_berhasil(): void
    {
        $this->makeHeadmaster($this->makeSchool(), '1001');

        $this->postJson('/api/v1/auth/login/staff', ['username' => '1001', 'password' => 'secret'])
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['access_token', 'token_type', 'user']]);
    }

    /** @test */
    public function login_staff_headmaster_inactive_ditolak_403(): void
    {
        $this->makeHeadmaster($this->makeSchool(), '1001', 'inactive');

        $response = $this->postJson('/api/v1/auth/login/staff', ['username' => '1001', 'password' => 'secret']);

        $this->assertInactiveRejected($response);
        $response->assertJsonMissingPath('data.access_token');
    }

    /** @test */
    public function login_staff_teacher_inactive_ditolak_403(): void
    {
        $this->makeTeacher($this->makeSchool(), '2001', 'inactive');

        $response = $this->postJson('/api/v1/auth/login/staff', ['username' => '2001', 'password' => 'secret']);

        $this->assertInactiveRejected($response);
    }

    /** @test */
    public function login_staff_inactive_dengan_password_salah_tetap_401_bukan_403(): void
    {
        $this->makeTeacher($this->makeSchool(), '2001', 'inactive');

        $this->postJson('/api/v1/auth/login/staff', ['username' => '2001', 'password' => 'salah'])
            ->assertStatus(401);
    }

    /** @test */
    public function super_admin_login_tetap_berhasil(): void
    {
        $this->makeSuperAdmin('sa_test');

        $this->postJson('/api/v1/auth/login/staff', ['username' => 'sa_test', 'password' => 'secret'])
            ->assertStatus(200);
    }

    // ---------- F-14: token lama ----------

    /** @test */
    public function token_lama_headmaster_ditolak_setelah_menjadi_inactive(): void
    {
        $school = $this->makeSchool();
        $hmUser = $this->makeHeadmaster($school, '1001');
        $token = $this->bearer($hmUser);

        $this->getAs('/api/v1/auth/me', $token)->assertStatus(200);
        $this->getAs('/api/v1/teachers', $token)->assertStatus(200);

        Headmaster::where('user_id', $hmUser->id)->update(['status' => 'inactive']);

        $this->assertInactiveRejected($this->getAs('/api/v1/auth/me', $token));
        $this->assertInactiveRejected($this->getAs('/api/v1/teachers', $token));
        $this->assertDatabaseHas('headmasters', ['user_id' => $hmUser->id, 'status' => 'inactive']);
    }

    /** @test */
    public function token_lama_teacher_ditolak_setelah_inactive_lewat_endpoint_lifecycle(): void
    {
        $school = $this->makeSchool();
        $hmUser = $this->makeHeadmaster($school, '1001');
        [$teacherUser, $teacher] = $this->makeTeacher($school, '2001');

        $teacherToken = $this->bearer($teacherUser);
        $hmToken = $this->bearer($hmUser);

        $this->getAs('/api/v1/auth/me', $teacherToken)->assertStatus(200);

        $this->postAs("/api/v1/teachers/{$teacher->id}/inactive", $hmToken)->assertStatus(200);

        $this->assertInactiveRejected($this->getAs('/api/v1/auth/me', $teacherToken));
        $this->assertDatabaseHas('teachers', ['id' => $teacher->id, 'status' => 'inactive']);
        // Headmaster pelaku tidak terpengaruh.
        $this->getAs('/api/v1/auth/me', $hmToken)->assertStatus(200);
    }

    /** @test */
    public function suksesi_headmaster_oleh_super_admin_mematikan_token_headmaster_lama_dan_histori_tetap(): void
    {
        $school = $this->makeSchool();
        $oldHmUser = $this->makeHeadmaster($school, '1001');
        $saUser = $this->makeSuperAdmin();

        $oldToken = $this->bearer($oldHmUser);
        $saToken = $this->bearer($saUser);

        $this->postAs('/api/v1/headmasters', $saToken, [
            'school_id' => $school->id, 'name' => 'Kepsek Baru', 'nip' => '1002', 'gender' => 'perempuan',
        ])->assertSuccessful();

        $this->assertInactiveRejected($this->getAs('/api/v1/auth/me', $oldToken));
        $this->assertDatabaseHas('headmasters', ['user_id' => $oldHmUser->id, 'status' => 'inactive']);
        $this->assertDatabaseHas('users', ['id' => $oldHmUser->id]);

        // Pengganti bisa login; SA tetap berfungsi.
        $this->postJson('/api/v1/auth/login/staff', ['username' => '1002', 'password' => '1002'])->assertStatus(200);
        $this->getAs('/api/v1/headmasters', $saToken)->assertStatus(200);
    }

    /** @test */
    public function super_admin_tetap_bisa_reset_password_headmaster_inactive(): void
    {
        $school = $this->makeSchool();
        $hmUser = $this->makeHeadmaster($school, '1001', 'inactive');
        $hm = Headmaster::where('user_id', $hmUser->id)->first();
        $saToken = $this->bearer($this->makeSuperAdmin());

        $this->postAs("/api/v1/headmasters/{$hm->id}/reset-password", $saToken, ['new_password' => self::NEW_PASSWORD])
            ->assertStatus(200);
    }

    /** @test */
    public function headmaster_aktif_tetap_bisa_reset_password_teacher_inactive(): void
    {
        $school = $this->makeSchool();
        $hmToken = $this->bearer($this->makeHeadmaster($school, '1001'));
        [, $teacher] = $this->makeTeacher($school, '2001', 'inactive');

        $this->postAs("/api/v1/teachers/{$teacher->id}/reset-password", $hmToken, ['new_password' => self::NEW_PASSWORD])
            ->assertStatus(200);
    }

    // ---------- F-24: Student ----------

    /** @test */
    public function login_student_inactive_dan_graduated_tetap_403(): void
    {
        $school = $this->makeSchool();
        $this->makeStudent($school, '0051110001', 'inactive');
        $this->makeStudent($school, '0051110002', 'graduated');

        foreach (['0051110001', '0051110002'] as $nisn) {
            $this->postJson('/api/v1/auth/login/student/password', ['nisn' => $nisn, 'password' => $nisn])
                ->assertStatus(403);
        }
    }

    /** @test */
    public function token_lama_student_ditolak_setelah_inactive_atau_graduated_dan_data_tetap(): void
    {
        $school = $this->makeSchool();
        [$userA, $studentA] = $this->makeStudent($school, '0051110001');
        [$userB, $studentB] = $this->makeStudent($school, '0051110002');
        $tokenA = $this->bearer($userA);
        $tokenB = $this->bearer($userB);

        $this->getAs('/api/v1/auth/me', $tokenA)->assertStatus(200)->assertJsonPath('data.role', 'student');
        $this->getAs('/api/v1/auth/me', $tokenB)->assertStatus(200);

        $studentA->update(['status' => 'inactive']);
        $studentB->update(['status' => 'graduated']);

        $this->assertInactiveRejected($this->getAs('/api/v1/auth/me', $tokenA));
        $this->assertInactiveRejected($this->getAs('/api/v1/auth/me', $tokenB));
        $this->assertDatabaseHas('students', ['id' => $studentA->id, 'nisn' => '0051110001', 'status' => 'inactive']);
        $this->assertDatabaseHas('students', ['id' => $studentB->id, 'nisn' => '0051110002', 'status' => 'graduated']);
    }

    /** @test */
    public function student_aktif_tetap_punya_akses_normal(): void
    {
        [$user] = $this->makeStudent($this->makeSchool(), '0051110001');

        $this->getAs('/api/v1/auth/me', $this->bearer($user))
            ->assertStatus(200)
            ->assertJsonPath('data.nisn', '0051110001');
    }

    // ---------- F-29: change / reset password ----------

    /** @test */
    public function change_password_super_admin_mencabut_token_lama_dan_login_ulang_berhasil(): void
    {
        $sa = $this->makeSuperAdmin('sa_test');
        $oldToken = $this->bearer($sa);

        $this->postAs('/api/v1/auth/change-password', $oldToken, ['new_password' => self::NEW_PASSWORD])
            ->assertStatus(200);

        $this->assertSessionRejected($this->getAs('/api/v1/auth/me', $oldToken));

        $this->postJson('/api/v1/auth/login/staff', ['username' => 'sa_test', 'password' => 'secret'])
            ->assertStatus(401);

        $login = $this->postJson('/api/v1/auth/login/staff', ['username' => 'sa_test', 'password' => self::NEW_PASSWORD])
            ->assertStatus(200);

        $this->getAs('/api/v1/auth/me', $login->json('data.access_token'))->assertStatus(200);
    }

    /** @test */
    public function reset_password_teacher_oleh_headmaster_mencabut_token_teacher_bukan_token_headmaster(): void
    {
        $school = $this->makeSchool();
        $hmToken = $this->bearer($this->makeHeadmaster($school, '1001'));
        [$teacherUser, $teacher] = $this->makeTeacher($school, '2001');
        $teacherOldToken = $this->bearer($teacherUser);

        $this->postAs("/api/v1/teachers/{$teacher->id}/reset-password", $hmToken, ['new_password' => self::NEW_PASSWORD])
            ->assertStatus(200);

        $this->assertSessionRejected($this->getAs('/api/v1/auth/me', $teacherOldToken));
        $this->getAs('/api/v1/auth/me', $hmToken)->assertStatus(200);

        $this->postJson('/api/v1/auth/login/staff', ['username' => '2001', 'password' => self::NEW_PASSWORD])
            ->assertStatus(200);
    }

    /** @test */
    public function reset_password_headmaster_oleh_super_admin_mencabut_token_headmaster_bukan_token_sa(): void
    {
        $school = $this->makeSchool();
        $hmUser = $this->makeHeadmaster($school, '1001');
        $hm = Headmaster::where('user_id', $hmUser->id)->first();
        $hmOldToken = $this->bearer($hmUser);
        $saToken = $this->bearer($this->makeSuperAdmin());

        $this->postAs("/api/v1/headmasters/{$hm->id}/reset-password", $saToken, ['new_password' => self::NEW_PASSWORD])
            ->assertStatus(200);

        $this->assertSessionRejected($this->getAs('/api/v1/auth/me', $hmOldToken));
        $this->getAs('/api/v1/auth/me', $saToken)->assertStatus(200);

        $this->postJson('/api/v1/auth/login/staff', ['username' => '1001', 'password' => self::NEW_PASSWORD])
            ->assertStatus(200);
    }

    /** @test */
    public function reset_password_student_oleh_teacher_mencabut_token_student_bukan_token_teacher(): void
    {
        [$teacherUser, $studentUser, $student] = $this->makeTeacherWithStudent();
        $teacherToken = $this->bearer($teacherUser);
        $studentOldToken = $this->bearer($studentUser);

        $this->postAs("/api/v1/students/{$student->id}/reset-password", $teacherToken, ['new_password' => self::NEW_PASSWORD])
            ->assertStatus(200);

        $this->assertSessionRejected($this->getAs('/api/v1/auth/me', $studentOldToken));
        $this->getAs('/api/v1/auth/me', $teacherToken)->assertStatus(200);

        $this->postJson('/api/v1/auth/login/student/password', ['nisn' => '0051230001', 'password' => self::NEW_PASSWORD])
            ->assertStatus(200);
    }

    /** @test */
    public function command_admin_reset_password_mencabut_token_lama_super_admin(): void
    {
        $sa = $this->makeSuperAdmin('sa_cli');
        $oldToken = $this->bearer($sa);

        $this->artisan('admin:reset-password', ['username' => 'sa_cli'])
            ->expectsQuestion('Masukkan password baru (min 8 karakter)', self::NEW_PASSWORD)
            ->assertExitCode(0);

        $this->assertSessionRejected($this->getAs('/api/v1/auth/me', $oldToken));
        $this->postJson('/api/v1/auth/login/staff', ['username' => 'sa_cli', 'password' => self::NEW_PASSWORD])
            ->assertStatus(200);
    }

    // ---------- regresi & sisi negatif mekanisme ----------

    /** @test */
    public function logout_tetap_mencabut_token_yang_dipakai(): void
    {
        $token = $this->bearer($this->makeSuperAdmin());

        $this->postAs('/api/v1/auth/logout', $token)->assertStatus(200);

        $this->assertSessionRejected($this->getAs('/api/v1/auth/me', $token));
    }

    /** @test */
    public function token_dengan_sidik_jari_password_salah_ditolak_401(): void
    {
        $user = $this->makeSuperAdmin();
        $token = (string) auth('api')->claims(['pwd' => 'bukan-sidik-jari-yang-benar'])->login($user);
        auth('api')->claims([]); // custom claims bersifat sticky pada singleton JWT

        $this->assertSessionRejected($this->getAs('/api/v1/auth/me', $token));
    }

    /** @test */
    public function token_lama_tanpa_claim_pwd_ditolak_401_fail_closed(): void
    {
        $user = $this->makeSuperAdmin();

        $payload = JWTFactory::sub($user->id)->make();
        $legacyToken = JWTAuth::manager()->encode($payload)->get();

        $this->assertSessionRejected($this->getAs('/api/v1/auth/me', $legacyToken));
    }

    /** @test */
    public function tanpa_token_tetap_401(): void
    {
        $this->assertSessionRejected($this->getJson('/api/v1/auth/me'));
    }
}
