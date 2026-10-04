<?php

namespace Tests\Feature\School;

use App\Models\AcademicYear;
use App\Models\Rombel;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolFixtures;
use Tests\TestCase;

// Stage 3 / Domain 3 - Rombel.
//
// F-19 (keputusan FINAL Matrix v3.1): setelah Rombel punya assignment (Teacher ATAU Student, aktif
//       maupun historis), academic_year_id dan grade_level tidak boleh berubah lewat PATCH. Rombel
//       tanpa assignment tetap bebas. Kode: 409 rombel_field_locked.
// F-20: grade_level punya batas atas = maksimum integer 32-bit kolom DB (SoT tidak menetapkan
//       rentang bisnis), jadi 2147483648 -> 422, bukan 500.
// F-21 (keputusan FINAL Matrix v3.1): Teacher hanya melihat Rombel dari active assignment miliknya;
//       tanpa active assignment -> kosong; di luar scope -> 404 (konvensi §26); Headmaster tidak berubah.
//
// STATUS EKSEKUSI: ditulis di sandbox tanpa PHP/PostgreSQL; belum dijalankan. Hasil lihat laporan patch.

class RombelIntegrityTest extends TestCase
{
    use RefreshDatabase, CreatesSchoolFixtures;

    protected function world(): array
    {
        $school = $this->fxSchool();
        $year = $this->fxAcademicYear($school);
        $otherYear = AcademicYear::create([
            'school_id' => $school->id, 'name' => '2027/2028', 'start_date' => '2027-07-01',
            'end_date' => '2028-06-30', 'is_active' => false,
        ]);

        return [
            'school' => $school, 'year' => $year, 'otherYear' => $otherYear,
            'hm' => $this->fxHeadmaster($school, '1001'),
            'r1' => $this->fxRombel($school, $year, 'VII-A'), // grade_level 7
            'r2' => $this->fxRombel($school, $year, 'VII-B'),
        ];
    }

    protected function patchRombel(User $hm, Rombel $rombel, array $body)
    {
        return $this->patchJson("/api/v1/rombels/{$rombel->id}", $body, ['Authorization' => 'Bearer ' . $this->fxToken($hm)]);
    }

    // ---------- F-19 ----------

    /** @test */
    public function f19_rombel_dengan_teacher_assignment_aktif_grade_level_dan_tahun_ajaran_terkunci_409(): void
    {
        $w = $this->world();
        $this->fxTeacherAssignment($this->fxTeacher($w['school'], '2001')->teacher, $w['r1']);

        $this->patchRombel($w['hm'], $w['r1'], ['grade_level' => 8])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'rombel_field_locked');
        $this->patchRombel($w['hm'], $w['r1'], ['academic_year_id' => $w['otherYear']->id])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'rombel_field_locked');

        $this->assertSame(7, $w['r1']->fresh()->grade_level);
        $this->assertSame($w['year']->id, $w['r1']->fresh()->academic_year_id);
    }

    /** @test */
    public function f19_assignment_student_saja_atau_assignment_historis_juga_mengunci(): void
    {
        $w = $this->world();
        // r1: hanya Student assignment 'ended' (historis). r2: hanya Teacher assignment is_active=false.
        $student = $this->fxStudent($w['school'], '0051110001')->student;
        $this->fxStudentAssignment($student, $w['r1'])->update(['status' => 'ended']);
        $this->fxTeacherAssignment($this->fxTeacher($w['school'], '2001')->teacher, $w['r2'])->update(['is_active' => false]);

        foreach (['r1', 'r2'] as $key) {
            $this->patchRombel($w['hm'], $w[$key], ['grade_level' => 9])
                ->assertStatus(409)
                ->assertJsonPath('error.code', 'rombel_field_locked');
            $this->assertSame(7, $w[$key]->fresh()->grade_level);
        }
    }

    /** @test */
    public function f19_rombel_tanpa_assignment_tetap_bisa_ubah_grade_level_dan_tahun_ajaran(): void
    {
        $w = $this->world();

        $this->patchRombel($w['hm'], $w['r1'], ['grade_level' => 8, 'academic_year_id' => $w['otherYear']->id])
            ->assertStatus(200)
            ->assertJsonPath('data.grade_level', 8)
            ->assertJsonPath('data.academic_year_id', $w['otherYear']->id);
    }

    /** @test */
    public function f19_rombel_ber_assignment_tetap_bisa_ganti_name_dan_nilai_yang_sama_bukan_perubahan(): void
    {
        $w = $this->world();
        $this->fxTeacherAssignment($this->fxTeacher($w['school'], '2001')->teacher, $w['r1']);

        $this->patchRombel($w['hm'], $w['r1'], ['name' => 'VII-A Revisi'])->assertStatus(200)->assertJsonPath('data.name', 'VII-A Revisi');
        // Klien mengirim ulang objek utuh dengan nilai yang sama: tidak ada perubahan, tidak ditolak.
        $this->patchRombel($w['hm'], $w['r1'], ['grade_level' => 7, 'academic_year_id' => $w['year']->id])->assertStatus(200);
    }

    /** @test */
    public function f19_patch_campuran_name_dan_field_terkunci_ditolak_utuh_name_tidak_ikut_berubah(): void
    {
        $w = $this->world();
        $this->fxTeacherAssignment($this->fxTeacher($w['school'], '2001')->teacher, $w['r1']);

        $this->patchRombel($w['hm'], $w['r1'], ['name' => 'Nama Baru', 'grade_level' => 8])->assertStatus(409);

        $this->assertSame('VII-A', $w['r1']->fresh()->name);
        $this->assertSame(7, $w['r1']->fresh()->grade_level);
    }

    // ---------- F-20 ----------

    /** @test */
    public function f20_grade_level_di_atas_batas_integer_422_bukan_500_dan_batas_itu_sendiri_diterima(): void
    {
        $w = $this->world();
        $token = $this->fxToken($w['hm']);
        $body = fn (int $grade, string $name) => [
            'school_id' => $w['school']->id, 'name' => $name, 'academic_year_id' => $w['year']->id, 'grade_level' => $grade,
        ];

        $this->apiPost('/api/v1/rombels', $token, $body(2147483648, 'X-1'))
            ->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
        $this->apiPost('/api/v1/rombels', $token, $body(2147483647, 'X-2'))->assertStatus(201);
        $this->apiPost('/api/v1/rombels', $token, $body(12, 'XII-A'))->assertStatus(201);

        $this->patchRombel($w['hm'], $w['r2'], ['grade_level' => 2147483648])
            ->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
        $this->assertSame(7, $w['r2']->fresh()->grade_level);
    }

    // ---------- F-21 ----------

    /** @test */
    public function f21_teacher_hanya_menerima_rombel_dari_active_assignment_miliknya(): void
    {
        $w = $this->world();
        $teacher = $this->fxTeacher($w['school'], '2001');
        $this->fxTeacherAssignment($teacher->teacher, $w['r1']);

        $response = $this->apiGet('/api/v1/rombels', $this->fxToken($teacher))->assertStatus(200);

        $this->assertSame([$w['r1']->id], collect($response->json('data'))->pluck('id')->all());
    }

    /** @test */
    public function f21_teacher_tanpa_active_assignment_menerima_daftar_kosong_bukan_error(): void
    {
        $w = $this->world();
        $none = $this->fxTeacher($w['school'], '2001');
        $closed = $this->fxTeacher($w['school'], '2002');
        $this->fxTeacherAssignment($closed->teacher, $w['r1'])->update(['is_active' => false]);

        foreach ([$none, $closed] as $teacher) {
            $this->apiGet('/api/v1/rombels', $this->fxToken($teacher))
                ->assertStatus(200)
                ->assertJsonCount(0, 'data');
        }
    }

    /** @test */
    public function f21_assignment_di_academic_year_tidak_aktif_bukan_scope_aktif_daftar_kosong(): void
    {
        $w = $this->world();
        $oldRombel = $this->fxRombel($w['school'], $w['otherYear'], 'VI-A'); // AY non-aktif
        $teacher = $this->fxTeacher($w['school'], '2001');
        $this->fxTeacherAssignment($teacher->teacher, $oldRombel);

        $this->apiGet('/api/v1/rombels', $this->fxToken($teacher))->assertStatus(200)->assertJsonCount(0, 'data');
    }

    /** @test */
    public function f21_detail_rombel_di_luar_scope_teacher_404_sama_dengan_id_tidak_ada_dan_rombel_sendiri_200(): void
    {
        $w = $this->world();
        $teacher = $this->fxTeacher($w['school'], '2001');
        $this->fxTeacherAssignment($teacher->teacher, $w['r1']);
        $token = $this->fxToken($teacher);

        $this->apiGet("/api/v1/rombels/{$w['r1']->id}", $token)->assertStatus(200);

        $outside = $this->apiGet("/api/v1/rombels/{$w['r2']->id}", $token);
        $missing = $this->apiGet('/api/v1/rombels/999999', $token);
        $outside->assertStatus(404)->assertJsonPath('error.code', 'not_found');
        $this->assertSame($missing->json('error.message'), $outside->json('error.message'));
    }

    /** @test */
    public function f21_headmaster_tetap_melihat_seluruh_rombel_sekolahnya_dan_tidak_sekolah_lain(): void
    {
        $w = $this->world();
        $schoolB = $this->fxSchool('SD Dua');
        $yearB = AcademicYear::create([
            'school_id' => $schoolB->id, 'name' => '2026/2027', 'start_date' => '2026-07-01',
            'end_date' => '2027-06-30', 'is_active' => true,
        ]);
        $this->fxRombel($schoolB, $yearB, 'VII-A');

        $response = $this->apiGet('/api/v1/rombels', $this->fxToken($w['hm']))->assertStatus(200);

        $this->assertEqualsCanonicalizing([$w['r1']->id, $w['r2']->id], collect($response->json('data'))->pluck('id')->all());
    }
}
