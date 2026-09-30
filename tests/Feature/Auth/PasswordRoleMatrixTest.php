<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesSchoolFixtures;
use Tests\TestCase;

// STAGE 0-C - menutup sebagian TEST GAP F-28 (AUTH-003): sisi NEGATIF hierarki
// change/reset password + validasi. SoT: Authorization Matrix #47-#50; API
// Contract v1.2 §10 (403 eksplisit untuk Change Own Password non-Super-Admin;
// Reset Password: 403 bila target di luar scope, 404 bila tidak ditemukan).
//
//   #47 Change Own Password : hanya Super Admin        -> lainnya 403 (bukan 404)
//   #48 Reset Headmaster    : hanya Super Admin
//   #49 Reset Teacher       : hanya Headmaster (own school)
//   #50 Reset Student       : hanya Teacher (own active rombel)
//
// Sisi POSITIF + skenario F-29 (token target dicabut, sesi admin tetap) sudah
// dicakup SessionValidityTest; HM->Teacher own/cross-school/404 dicakup
// TeacherResetPasswordScopeTest. Tidak diduplikasi di sini.
//
// STATUS EKSEKUSI: dijalankan di PHP + PostgreSQL; hasil terkini lihat docs/testing/BASELINE-STAGE-0C.md

class PasswordRoleMatrixTest extends TestCase
{
    use RefreshDatabase, CreatesSchoolFixtures;

    private const NEW_PASSWORD = 'PasswordBaru123';

    /**
     * 1 school, 1 tahun ajaran aktif, 2 rombel. Teacher memegang rombel A.
     * studentA di rombel A (own scope Teacher), studentB di rombel B (di luar scope).
     */
    protected function world(): array
    {
        $school = $this->fxSchool();
        $year = $this->fxAcademicYear($school);
        $rombelA = $this->fxRombel($school, $year, 'VII-A');
        $rombelB = $this->fxRombel($school, $year, 'VII-B');

        $sa = $this->fxSuperAdmin('sa_pw');
        $hm = $this->fxHeadmaster($school, '1001');
        $teacher = $this->fxTeacher($school, '2001');
        $this->fxTeacherAssignment($teacher->teacher, $rombelA);

        $studentA = $this->fxStudent($school, '0051110001');
        $this->fxStudentAssignment($studentA->student, $rombelA);

        $studentB = $this->fxStudent($school, '0051110002');
        $this->fxStudentAssignment($studentB->student, $rombelB);

        return compact('sa', 'hm', 'teacher', 'studentA', 'studentB');
    }

    protected function assertPasswordUnchanged($user, string $oldPassword): void
    {
        $fresh = $user->fresh();

        $this->assertTrue(Hash::check($oldPassword, $fresh->password), 'password lama harus tetap berlaku');
        $this->assertFalse(Hash::check(self::NEW_PASSWORD, $fresh->password), 'password baru tidak boleh terpasang');
    }

    // ---------- #47 Change Own Password ----------

    /** @test */
    public function change_password_selain_super_admin_ditolak_403_bukan_404(): void
    {
        $w = $this->world();

        foreach ([[$w['hm'], 'secret'], [$w['teacher'], 'secret'], [$w['studentA'], '0051110001']] as [$user, $old]) {
            $this->apiPost('/api/v1/auth/change-password', $this->fxToken($user), ['new_password' => self::NEW_PASSWORD])
                ->assertStatus(403)
                ->assertJsonPath('error.code', 'forbidden');

            $this->assertPasswordUnchanged($user, $old);
        }
    }

    /** @test */
    public function change_password_tanpa_token_401(): void
    {
        $this->apiPost('/api/v1/auth/change-password', null, ['new_password' => self::NEW_PASSWORD])
            ->assertStatus(401);
    }

    /** @test */
    public function change_password_super_admin_password_pendek_atau_kosong_422_dan_tidak_berubah(): void
    {
        $w = $this->world();
        $token = $this->fxToken($w['sa']);

        $this->apiPost('/api/v1/auth/change-password', $token, ['new_password' => 'pendek'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->apiPost('/api/v1/auth/change-password', $token, [])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->assertPasswordUnchanged($w['sa'], 'secret');
    }

    // ---------- #48 Reset Headmaster ----------

    /** @test */
    public function reset_headmaster_selain_super_admin_ditolak_403(): void
    {
        $w = $this->world();
        $hmProfileId = $w['hm']->headmaster->id;

        foreach ([$w['hm'], $w['teacher'], $w['studentA']] as $actor) {
            $this->apiPost("/api/v1/headmasters/{$hmProfileId}/reset-password", $this->fxToken($actor), ['new_password' => self::NEW_PASSWORD])
                ->assertStatus(403)
                ->assertJsonPath('error.code', 'forbidden');
        }

        $this->assertPasswordUnchanged($w['hm'], 'secret');
    }

    /** @test */
    public function reset_headmaster_super_admin_id_tidak_ada_404_dan_password_pendek_422(): void
    {
        $w = $this->world();
        $token = $this->fxToken($w['sa']);

        $this->apiPost('/api/v1/headmasters/999999999/reset-password', $token, ['new_password' => self::NEW_PASSWORD])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'not_found');

        $this->apiPost("/api/v1/headmasters/{$w['hm']->headmaster->id}/reset-password", $token, ['new_password' => 'pendek'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->assertPasswordUnchanged($w['hm'], 'secret');
    }

    // ---------- #49 Reset Teacher ----------

    /** @test */
    public function reset_teacher_selain_headmaster_ditolak_403(): void
    {
        $w = $this->world();
        $teacherProfileId = $w['teacher']->teacher->id;

        foreach ([$w['sa'], $w['teacher'], $w['studentA']] as $actor) {
            $this->apiPost("/api/v1/teachers/{$teacherProfileId}/reset-password", $this->fxToken($actor), ['new_password' => self::NEW_PASSWORD])
                ->assertStatus(403)
                ->assertJsonPath('error.code', 'forbidden');
        }

        $this->assertPasswordUnchanged($w['teacher'], 'secret');
    }

    /** @test */
    public function reset_teacher_headmaster_password_pendek_422_dan_tidak_berubah(): void
    {
        $w = $this->world();

        $this->apiPost("/api/v1/teachers/{$w['teacher']->teacher->id}/reset-password", $this->fxToken($w['hm']), ['new_password' => 'pendek'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->assertPasswordUnchanged($w['teacher'], 'secret');
    }

    // ---------- #50 Reset Student ----------

    /** @test */
    public function reset_student_selain_teacher_ditolak_403(): void
    {
        $w = $this->world();
        $studentProfileId = $w['studentA']->student->id;

        foreach ([$w['sa'], $w['hm'], $w['studentA']] as $actor) {
            $this->apiPost("/api/v1/students/{$studentProfileId}/reset-password", $this->fxToken($actor), ['new_password' => self::NEW_PASSWORD])
                ->assertStatus(403)
                ->assertJsonPath('error.code', 'forbidden');
        }

        $this->assertPasswordUnchanged($w['studentA'], '0051110001');
    }

    /** @test */
    public function reset_student_teacher_lintas_rombel_ditolak_403_dan_password_tidak_berubah(): void
    {
        $w = $this->world();

        $this->apiPost("/api/v1/students/{$w['studentB']->student->id}/reset-password", $this->fxToken($w['teacher']), ['new_password' => self::NEW_PASSWORD])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');

        $this->assertPasswordUnchanged($w['studentB'], '0051110002');
    }

    /** @test */
    public function reset_student_teacher_id_tidak_ada_404_dan_password_pendek_422(): void
    {
        $w = $this->world();
        $token = $this->fxToken($w['teacher']);

        $this->apiPost('/api/v1/students/999999999/reset-password', $token, ['new_password' => self::NEW_PASSWORD])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'not_found');

        $this->apiPost("/api/v1/students/{$w['studentA']->student->id}/reset-password", $token, ['new_password' => 'pendek'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->assertPasswordUnchanged($w['studentA'], '0051110001');
    }
}
