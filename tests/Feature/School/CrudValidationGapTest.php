<?php

namespace Tests\Feature\School;

use App\Models\AcademicYear;
use App\Models\Headmaster;
use App\Models\Rombel;
use App\Models\School;
use App\Models\Teacher;
use App\Models\TeacherClassAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSchoolFixtures;
use Tests\TestCase;

// Stage 6 / Bagian 3: menutup gap regression PHPUnit yang MASIH terbuka dari Matrix §5 (SCHOOL-001,
// HEADMASTER-001, TEACHER-001, ACADEMICYEAR-001, ROMBEL-001 delete-guard, TEACHER-ASSIGNMENT-001,
// TEACHER-LIFECYCLE-001). Hasil verifikasi grep tests/ (HEAD efbf95b..c8be555): perilaku di bawah ini
// SEBELUMNYA hanya teruji lewat Postman TEST-002 (627 test, baseline 617 passed), belum ada test PHPUnit;
// tanpa test ini perubahan kode yang menyentuhnya tidak punya jaring pengaman otomatis di CI.
//
// Sengaja TIDAK diulang: show() lintas sekolah Rombel/AY/Assignment (ShowCrossSchoolScopeTest), matriks
// negatif reset password (PasswordRoleMatrixTest, TeacherResetPasswordScopeTest), concurrent activate AY (F-03),
// concurrent insert assignment (F-16/F-18), duplikat NIP (NipUsernameCollisionTest), pagination (F-15).
//
// STATUS EKSEKUSI: ditulis di sandbox tanpa PHP/PostgreSQL; hasil run lihat laporan patch. Ekspektasi status/kode
// diambil dari API Contract v1.2 dan dari assertion Postman TEST-002 yang lulus di baseline.

class CrudValidationGapTest extends TestCase
{
    use RefreshDatabase, CreatesSchoolFixtures;

    protected function authed(User $user): array
    {
        return ['Authorization' => 'Bearer ' . $this->fxToken($user)];
    }

    protected function otherSchool(): array
    {
        $school = $this->fxSchool('SD Lain');
        $year = $this->fxAcademicYear($school);

        return [$school, $year, $this->fxRombel($school, $year, 'VII-A')];
    }

    // ---------- SCHOOL-001: detail ----------

    /** @test */
    public function school_detail_super_admin_200_dan_id_tidak_ada_404(): void
    {
        $school = $this->fxSchool();
        $headers = $this->authed($this->fxSuperAdmin());

        $this->getJson("/api/v1/schools/{$school->id}", $headers)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $school->id)
            ->assertJsonPath('data.name', 'SD Satu');
        $this->getJson('/api/v1/schools/999999999', $headers)
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'not_found');
    }

    /** @test */
    public function school_detail_dan_list_non_super_admin_403(): void
    {
        $school = $this->fxSchool();
        $headers = $this->authed($this->fxHeadmaster($school, '1001'));

        $this->getJson("/api/v1/schools/{$school->id}", $headers)->assertStatus(403);
        $this->getJson('/api/v1/schools', $headers)->assertStatus(403);
    }

    // ---------- HEADMASTER-001: validasi, 403, suksesi ----------

    /** @test */
    public function create_headmaster_validasi_gender_dan_school_422_dan_tidak_ada_yang_dibuat(): void
    {
        $school = $this->fxSchool();
        $headers = $this->authed($this->fxSuperAdmin());

        $cases = [
            'tanpa gender'     => ['school_id' => $school->id, 'name' => 'X', 'nip' => '9001'],
            'gender invalid'   => ['school_id' => $school->id, 'name' => 'X', 'nip' => '9002', 'gender' => 'pria'],
            'school tidak ada' => ['school_id' => 999999999, 'name' => 'X', 'nip' => '9003', 'gender' => 'laki-laki'],
        ];

        foreach ($cases as $label => $body) {
            $this->postJson('/api/v1/headmasters', $body, $headers)
                ->assertStatus(422)
                ->assertJsonPath('error.code', 'validation_failed');
        }

        $this->assertSame(0, Headmaster::count(), 'tidak boleh ada Headmaster yang dibuat');
        $this->assertSame(0, User::where('role', 'headmaster')->count());
    }

    /** @test */
    public function create_headmaster_oleh_non_super_admin_403(): void
    {
        $school = $this->fxSchool();

        $this->postJson('/api/v1/headmasters', [
            'school_id' => $school->id, 'name' => 'X', 'nip' => '9004', 'gender' => 'laki-laki',
        ], $this->authed($this->fxHeadmaster($school, '1001')))->assertStatus(403);

        $this->assertSame(1, Headmaster::count());
    }

    /** @test */
    public function suksesi_headmaster_lama_inactive_baru_active_tepat_satu_active_per_sekolah(): void
    {
        $school = $this->fxSchool();
        $old = $this->fxHeadmaster($school, '1001');
        $headers = $this->authed($this->fxSuperAdmin());

        $this->postJson('/api/v1/headmasters', [
            'school_id' => $school->id, 'name' => 'Pengganti', 'nip' => '1002', 'gender' => 'perempuan',
        ], $headers)->assertStatus(201);

        $this->assertSame('inactive', $old->headmaster->fresh()->status);
        $this->assertSame('active', Headmaster::where('nip', '1002')->value('status'));
        $this->assertSame(1, Headmaster::where('school_id', $school->id)->where('status', 'active')->count());
        $this->assertSame(2, Headmaster::where('school_id', $school->id)->count(), 'riwayat Headmaster lama tetap ada');
    }

    // ---------- TEACHER-001: validasi, scope, detail ----------

    /** @test */
    public function create_teacher_validasi_gender_422_dan_super_admin_403(): void
    {
        $school = $this->fxSchool();
        $hmHeaders = $this->authed($this->fxHeadmaster($school, '1001'));

        $this->postJson('/api/v1/teachers', ['school_id' => $school->id, 'name' => 'X', 'nip' => '9101'], $hmHeaders)
            ->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
        $this->postJson('/api/v1/teachers', ['school_id' => $school->id, 'name' => 'X', 'nip' => '9102', 'gender' => 'pria'], $hmHeaders)
            ->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');

        $this->postJson('/api/v1/teachers', [
            'school_id' => $school->id, 'name' => 'X', 'nip' => '9103', 'gender' => 'laki-laki',
        ], $this->authed($this->fxSuperAdmin()))->assertStatus(403);

        $this->assertSame(0, Teacher::count());
    }

    /** @test */
    public function create_teacher_untuk_sekolah_lain_403_dan_detail_teacher_sekolah_lain_404(): void
    {
        [$other] = $this->otherSchool();
        $school = $this->fxSchool();
        $hmHeaders = $this->authed($this->fxHeadmaster($school, '1001'));
        $foreign = $this->fxTeacher($other, '2101');

        $this->postJson('/api/v1/teachers', [
            'school_id' => $other->id, 'name' => 'X', 'nip' => '9104', 'gender' => 'laki-laki',
        ], $hmHeaders)->assertStatus(403);
        $this->assertDatabaseMissing('teachers', ['nip' => '9104']);

        $this->getJson("/api/v1/teachers/{$foreign->teacher->id}", $hmHeaders)
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'not_found');
        $this->getJson('/api/v1/teachers/999999999', $hmHeaders)->assertStatus(404);
    }

    // ---------- ACADEMICYEAR-001: validasi create ----------

    /** @test */
    public function create_academic_year_validasi_422_dan_tanggal_valid_201(): void
    {
        $school = $this->fxSchool();
        $headers = $this->authed($this->fxHeadmaster($school, '1001'));

        $this->postJson('/api/v1/academic-years', ['name' => 'X', 'start_date' => '2026-07-01', 'end_date' => '2026-01-01'], $headers)
            ->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
        $this->postJson('/api/v1/academic-years', ['name' => 'X', 'start_date' => '2026-07-01', 'end_date' => '2026-07-01'], $headers)
            ->assertStatus(422);
        $this->postJson('/api/v1/academic-years', ['start_date' => '2026-07-01', 'end_date' => '2027-06-30'], $headers)
            ->assertStatus(422);
        $this->assertSame(0, AcademicYear::count());

        $this->postJson('/api/v1/academic-years', ['name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30'], $headers)
            ->assertStatus(201)
            ->assertJsonPath('data.school_id', $school->id);
    }

    /** @test */
    public function teacher_tidak_bisa_menulis_academic_year_rombel_dan_assignment_403(): void
    {
        $school = $this->fxSchool();
        $year = $this->fxAcademicYear($school);
        $rombel = $this->fxRombel($school, $year, 'VII-A');
        $teacher = $this->fxTeacher($school, '2001');
        $assignment = $this->fxTeacherAssignment($teacher->teacher, $rombel);
        $headers = $this->authed($teacher);

        $this->postJson('/api/v1/academic-years', ['name' => 'X', 'start_date' => '2030-07-01', 'end_date' => '2031-06-30'], $headers)->assertStatus(403);
        $this->patchJson("/api/v1/academic-years/{$year->id}", ['name' => 'Y'], $headers)->assertStatus(403);
        $this->postJson('/api/v1/rombels', ['school_id' => $school->id, 'name' => 'X', 'academic_year_id' => $year->id, 'grade_level' => 7], $headers)->assertStatus(403);
        $this->patchJson("/api/v1/rombels/{$rombel->id}", ['name' => 'Y'], $headers)->assertStatus(403);
        $this->deleteJson("/api/v1/rombels/{$rombel->id}", [], $headers)->assertStatus(403);
        $this->postJson("/api/v1/teacher-class-assignments/{$assignment->id}/deactivate", [], $headers)->assertStatus(403);

        $this->assertSame('VII-A', $rombel->fresh()->name);
        $this->assertTrue($assignment->fresh()->is_active);
    }

    // ---------- ROMBEL-001: validasi + delete guard ----------

    /** @test */
    public function create_dan_patch_rombel_dengan_academic_year_sekolah_lain_422_academic_year_mismatch(): void
    {
        [, $yearOther] = $this->otherSchool();
        $school = $this->fxSchool();
        $headers = $this->authed($this->fxHeadmaster($school, '1001'));
        $year = $this->fxAcademicYear($school);
        $rombel = $this->fxRombel($school, $year, 'VII-A');

        $this->postJson('/api/v1/rombels', [
            'school_id' => $school->id, 'name' => 'X', 'academic_year_id' => $yearOther->id, 'grade_level' => 7,
        ], $headers)->assertStatus(422)->assertJsonPath('error.code', 'academic_year_mismatch');
        $this->patchJson("/api/v1/rombels/{$rombel->id}", ['academic_year_id' => $yearOther->id], $headers)
            ->assertStatus(422)->assertJsonPath('error.code', 'academic_year_mismatch');

        $this->assertSame($year->id, $rombel->fresh()->academic_year_id);
        $this->assertSame(1, Rombel::where('school_id', $school->id)->count());
    }

    /** @test */
    public function create_rombel_tanpa_grade_level_atau_name_422_dan_untuk_sekolah_lain_403(): void
    {
        [$other, $yearOther] = $this->otherSchool();
        $school = $this->fxSchool();
        $year = $this->fxAcademicYear($school);
        $headers = $this->authed($this->fxHeadmaster($school, '1001'));

        $this->postJson('/api/v1/rombels', ['school_id' => $school->id, 'name' => 'X', 'academic_year_id' => $year->id], $headers)->assertStatus(422);
        $this->postJson('/api/v1/rombels', ['school_id' => $school->id, 'academic_year_id' => $year->id, 'grade_level' => 7], $headers)->assertStatus(422);
        $this->postJson('/api/v1/rombels', [
            'school_id' => $other->id, 'name' => 'X', 'academic_year_id' => $yearOther->id, 'grade_level' => 7,
        ], $headers)->assertStatus(403);

        $this->assertSame(0, Rombel::where('school_id', $school->id)->count());
    }

    /**
     * Menjalankan $callback di dalam SAVEPOINT yang SELALU di-rollback.
     *
     * Kenapa perlu: RombelService::destroy() menangkap FK violation (SQLSTATE 23503) dan mengubahnya
     * menjadi 409. Di produksi itu aman (setiap request berdiri sendiri, tanpa transaksi pembungkus).
     * Di PHPUnit, RefreshDatabase membungkus seluruh test dalam SATU transaksi, dan PostgreSQL
     * menandai transaksi itu "aborted" setelah statement yang gagal, sehingga query berikutnya
     * (mis. assertDatabaseHas) ditolak dengan SQLSTATE 25P02. ROLLBACK TO SAVEPOINT memulihkannya.
     * Ini artefak lingkungan test, bukan bug produksi; perilaku yang diuji (409 + data utuh) tetap asli.
     */
    protected function withinSavepoint(callable $callback): void
    {
        DB::beginTransaction();

        try {
            $callback();
        } finally {
            DB::rollBack();
        }
    }

    /** @test */
    public function delete_rombel_yang_direferensikan_teacher_assignment_409_rombel_has_active_assignment(): void
    {
        $school = $this->fxSchool();
        $year = $this->fxAcademicYear($school);
        $rombel = $this->fxRombel($school, $year, 'VII-A');
        $this->fxTeacherAssignment($this->fxTeacher($school, '2001')->teacher, $rombel);
        $headers = $this->authed($this->fxHeadmaster($school, '1001'));

        $this->withinSavepoint(function () use ($rombel, $headers) {
            $this->deleteJson("/api/v1/rombels/{$rombel->id}", [], $headers)
                ->assertStatus(409)
                ->assertJsonPath('error.code', 'rombel_has_active_assignment');
        });

        $this->assertDatabaseHas('rombels', ['id' => $rombel->id]);
    }

    /** @test */
    public function delete_rombel_yang_direferensikan_student_assignment_juga_409(): void
    {
        $school = $this->fxSchool();
        $year = $this->fxAcademicYear($school);
        $rombel = $this->fxRombel($school, $year, 'VII-A');
        $this->fxStudentAssignment($this->fxStudent($school, '0051110001')->student, $rombel);
        $headers = $this->authed($this->fxHeadmaster($school, '1001'));

        $this->withinSavepoint(function () use ($rombel, $headers) {
            $this->deleteJson("/api/v1/rombels/{$rombel->id}", [], $headers)
                ->assertStatus(409)
                ->assertJsonPath('error.code', 'rombel_has_active_assignment');
        });

        $this->assertDatabaseHas('rombels', ['id' => $rombel->id]);
    }

    // ---------- TEACHER-ASSIGNMENT-001: validasi create/patch ----------

    protected function assignmentWorld(): array
    {
        $school = $this->fxSchool();
        $year = $this->fxAcademicYear($school);
        $year2 = AcademicYear::create([
            'school_id' => $school->id, 'name' => '2027/2028', 'start_date' => '2027-07-01',
            'end_date' => '2028-06-30', 'is_active' => false,
        ]);
        [$other, $yearOther, $rombelOther] = $this->otherSchool();

        return [
            'school' => $school, 'year' => $year, 'year2' => $year2,
            'rombel' => $this->fxRombel($school, $year, 'VII-A'),
            'teacher' => $this->fxTeacher($school, '2001')->teacher,
            'other' => $other, 'yearOther' => $yearOther, 'rombelOther' => $rombelOther,
            'headers' => $this->authed($this->fxHeadmaster($school, '1001')),
        ];
    }

    /** @test */
    public function post_assignment_rombel_sekolah_lain_atau_tahun_tidak_cocok_422_rombel_mismatch(): void
    {
        $w = $this->assignmentWorld();

        $this->postJson('/api/v1/teacher-class-assignments', [
            'teacher_id' => $w['teacher']->id, 'school_id' => $w['school']->id,
            'academic_year_id' => $w['year']->id, 'rombel_id' => $w['rombelOther']->id,
        ], $w['headers'])->assertStatus(422)->assertJsonPath('error.code', 'rombel_mismatch');

        $this->postJson('/api/v1/teacher-class-assignments', [
            'teacher_id' => $w['teacher']->id, 'school_id' => $w['school']->id,
            'academic_year_id' => $w['year2']->id, 'rombel_id' => $w['rombel']->id,
        ], $w['headers'])->assertStatus(422)->assertJsonPath('error.code', 'rombel_mismatch');

        $this->assertSame(0, TeacherClassAssignment::count());
    }

    /** @test */
    public function post_assignment_school_id_atau_teacher_sekolah_lain_403_dan_tanpa_rombel_422(): void
    {
        $w = $this->assignmentWorld();
        $foreignTeacher = $this->fxTeacher($w['other'], '2101')->teacher;

        $this->postJson('/api/v1/teacher-class-assignments', [
            'teacher_id' => $w['teacher']->id, 'school_id' => $w['other']->id,
            'academic_year_id' => $w['yearOther']->id, 'rombel_id' => $w['rombelOther']->id,
        ], $w['headers'])->assertStatus(403);

        $this->postJson('/api/v1/teacher-class-assignments', [
            'teacher_id' => $foreignTeacher->id, 'school_id' => $w['school']->id,
            'academic_year_id' => $w['year']->id, 'rombel_id' => $w['rombel']->id,
        ], $w['headers'])->assertStatus(403);

        $this->postJson('/api/v1/teacher-class-assignments', [
            'teacher_id' => $w['teacher']->id, 'school_id' => $w['school']->id, 'academic_year_id' => $w['year']->id,
        ], $w['headers'])->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');

        $this->assertSame(0, TeacherClassAssignment::count());
    }

    /** @test */
    public function patch_assignment_ke_rombel_sekolah_lain_422_dan_data_tidak_berubah(): void
    {
        $w = $this->assignmentWorld();
        $assignment = $this->fxTeacherAssignment($w['teacher'], $w['rombel']);

        $this->patchJson("/api/v1/teacher-class-assignments/{$assignment->id}", ['rombel_id' => $w['rombelOther']->id], $w['headers'])
            ->assertStatus(422);

        $this->assertSame($w['rombel']->id, $assignment->fresh()->rombel_id);
    }

    // ---------- TEACHER-LIFECYCLE-001: cascade lintas Academic Year + idempoten ----------

    /** @test */
    public function inactive_teacher_menutup_semua_assignment_aktif_lintas_tahun_ajaran_riwayat_tetap_ada(): void
    {
        $w = $this->assignmentWorld();
        $rombel2 = $this->fxRombel($w['school'], $w['year2'], 'VIII-A');
        $a1 = $this->fxTeacherAssignment($w['teacher'], $w['rombel']);   // tahun 2026/2027
        $a2 = $this->fxTeacherAssignment($w['teacher'], $rombel2);       // tahun 2027/2028 (aktif sah: satu per tahun)
        $this->assertSame(2, TeacherClassAssignment::where('teacher_id', $w['teacher']->id)->where('is_active', true)->count());

        $this->postJson("/api/v1/teachers/{$w['teacher']->id}/inactive", [], $w['headers'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'inactive');

        $this->assertFalse($a1->fresh()->is_active);
        $this->assertFalse($a2->fresh()->is_active);
        $this->assertSame(2, TeacherClassAssignment::where('teacher_id', $w['teacher']->id)->count(), 'riwayat tetap ada, tidak dihapus');
    }

    /** @test */
    public function inactive_teacher_dipanggil_ulang_idempoten_200_dan_tidak_ada_reaktivasi(): void
    {
        $w = $this->assignmentWorld();

        $this->postJson("/api/v1/teachers/{$w['teacher']->id}/inactive", [], $w['headers'])->assertStatus(200);
        $this->postJson("/api/v1/teachers/{$w['teacher']->id}/inactive", [], $w['headers'])->assertStatus(200);

        $this->assertSame('inactive', $w['teacher']->fresh()->status);
        $this->assertContains(
            $this->postJson("/api/v1/teachers/{$w['teacher']->id}/active", [], $w['headers'])->status(),
            [404, 405],
            'tidak ada endpoint reaktivasi'
        );
    }

    /** @test */
    public function inactive_teacher_oleh_non_headmaster_403_dan_tidak_mengubah_status(): void
    {
        $w = $this->assignmentWorld();
        $other = $this->fxTeacher($w['school'], '2002');

        foreach ([$this->authed($this->fxSuperAdmin()), $this->authed($other)] as $headers) {
            $this->postJson("/api/v1/teachers/{$w['teacher']->id}/inactive", [], $headers)->assertStatus(403);
        }

        $this->assertSame('active', $w['teacher']->fresh()->status);
    }
}
