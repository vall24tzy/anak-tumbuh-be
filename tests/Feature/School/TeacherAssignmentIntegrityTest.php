<?php

namespace Tests\Feature\School;

use App\Models\AcademicYear;
use App\Models\Rombel;
use App\Models\School;
use App\Models\Teacher;
use App\Models\TeacherClassAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSchoolFixtures;
use Tests\TestCase;

// Stage 3 / Domain 2 - Teacher Assignment. Urutan fix: F-16 -> F-18 -> F-17.
//
// F-16: POST konkuren Teacher+tahun sama -> dulu 500, harus 409 teacher_already_has_active_assignment.
// F-18 (keputusan FINAL Matrix v3.1): dalam satu Rombel pada satu Academic Year maksimal SATU
//       Teacher assignment aktif (= wali kelas sistem). Sekuensial -> 409; konkuren -> 409 (bukan 500);
//       index uq_tca_one_active_per_rombel_year ada di skema.
// F-17 (keputusan FINAL Matrix v3.1): assignment historis (is_active=false) tidak boleh di-PATCH;
//       POST assignment baru tetap berfungsi. Kode: 409 assignment_inactive_readonly.
//
// Simulasi balapan: listener `creating` menyisipkan baris aktif pesaing lewat query builder tepat
// sebelum INSERT milik service (pola test balapan F-13). Service lalu menabrak partial unique index.
// Transaksi service di-rollback utuh sehingga pesaing simulasi ikut terbuang.
//
// STATUS EKSEKUSI: ditulis di sandbox tanpa PHP/PostgreSQL; belum dijalankan. Hasil lihat laporan patch.

class TeacherAssignmentIntegrityTest extends TestCase
{
    use RefreshDatabase, CreatesSchoolFixtures;

    /** Satu sekolah, AY aktif, dua rombel, tiga Teacher, satu Headmaster. */
    protected function world(): array
    {
        $school = $this->fxSchool();
        $year = $this->fxAcademicYear($school);

        return [
            'school' => $school,
            'year'   => $year,
            'r1'     => $this->fxRombel($school, $year, 'VII-A'),
            'r2'     => $this->fxRombel($school, $year, 'VII-B'),
            't1'     => $this->fxTeacher($school, '2001')->teacher,
            't2'     => $this->fxTeacher($school, '2002')->teacher,
            't3'     => $this->fxTeacher($school, '2003')->teacher,
            'hm'     => $this->fxHeadmaster($school, '1001'),
        ];
    }

    protected function postAssignment(User $hm, Teacher $teacher, Rombel $rombel)
    {
        return $this->apiPost('/api/v1/teacher-class-assignments', $this->fxToken($hm), [
            'teacher_id' => $teacher->id, 'school_id' => $rombel->school_id,
            'academic_year_id' => $rombel->academic_year_id, 'rombel_id' => $rombel->id,
        ]);
    }

    protected function patchAssignment(User $hm, TeacherClassAssignment $a, array $body)
    {
        return $this->patchJson("/api/v1/teacher-class-assignments/{$a->id}", $body, ['Authorization' => 'Bearer ' . $this->fxToken($hm)]);
    }

    protected function activeFor(Rombel $rombel): int
    {
        return TeacherClassAssignment::where('rombel_id', $rombel->id)->where('is_active', true)->count();
    }

    protected function simulateCompetingInsert(array $row): void
    {
        TeacherClassAssignment::creating(function () use ($row) {
            DB::table('teacher_class_assignments')->insert($row + ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        });
    }

    // ---------- F-16 ----------

    /** @test */
    public function f16_teacher_sama_tahun_sama_sekuensial_tetap_409_kode_lama(): void
    {
        $w = $this->world();
        $this->postAssignment($w['hm'], $w['t1'], $w['r1'])->assertStatus(201);

        $this->postAssignment($w['hm'], $w['t1'], $w['r2'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'teacher_already_has_active_assignment');
        $this->assertSame(1, TeacherClassAssignment::where('teacher_id', $w['t1']->id)->count());
    }

    /** @test */
    public function f16_post_konkuren_teacher_dan_tahun_sama_409_bukan_500(): void
    {
        $w = $this->world();
        // Pesaing: Teacher yang sama, rombel lain, AY sama -> menabrak uq_tca_one_active_per_teacher_year.
        $this->simulateCompetingInsert([
            'teacher_id' => $w['t1']->id, 'school_id' => $w['school']->id,
            'academic_year_id' => $w['year']->id, 'rombel_id' => $w['r2']->id,
        ]);

        $this->postAssignment($w['hm'], $w['t1'], $w['r1'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'teacher_already_has_active_assignment');

        $this->assertSame(0, $this->activeFor($w['r1']));
    }

    // ---------- F-18 ----------

    /** @test */
    public function f18_rombel_sama_tahun_sama_teacher_aktif_kedua_409_spesifik_dan_tidak_tersimpan(): void
    {
        $w = $this->world();
        $this->postAssignment($w['hm'], $w['t1'], $w['r1'])->assertStatus(201);

        $this->postAssignment($w['hm'], $w['t2'], $w['r1'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'rombel_already_has_active_teacher');

        $this->assertSame(1, $this->activeFor($w['r1']));
        $this->assertSame(0, TeacherClassAssignment::where('teacher_id', $w['t2']->id)->count());
    }

    /** @test */
    public function f18_rombel_berbeda_tetap_boleh_dan_rombel_bebas_lagi_setelah_teacher_lama_deactivate(): void
    {
        $w = $this->world();
        $first = $this->postAssignment($w['hm'], $w['t1'], $w['r1']);
        $first->assertStatus(201);
        $this->postAssignment($w['hm'], $w['t2'], $w['r2'])->assertStatus(201); // rombel lain: boleh

        $this->apiPost('/api/v1/teacher-class-assignments/' . $first->json('data.id') . '/deactivate', $this->fxToken($w['hm']))->assertStatus(200);

        $this->postAssignment($w['hm'], $w['t3'], $w['r1'])->assertStatus(201);
        $this->assertSame(1, $this->activeFor($w['r1'])); // histori tetap ada, aktifnya satu
        $this->assertSame(2, TeacherClassAssignment::where('rombel_id', $w['r1']->id)->count());
    }

    /** @test */
    public function f18_post_konkuren_rombel_dan_tahun_sama_409_bukan_500(): void
    {
        $w = $this->world();
        // Pesaing: Teacher LAIN pada rombel yang sama -> menabrak uq_tca_one_active_per_rombel_year.
        $this->simulateCompetingInsert([
            'teacher_id' => $w['t3']->id, 'school_id' => $w['school']->id,
            'academic_year_id' => $w['year']->id, 'rombel_id' => $w['r1']->id,
        ]);

        $this->postAssignment($w['hm'], $w['t1'], $w['r1'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'rombel_already_has_active_teacher');

        $this->assertSame(0, TeacherClassAssignment::where('teacher_id', $w['t1']->id)->count());
    }

    /** @test */
    public function f18_patch_memindahkan_assignment_ke_rombel_yang_sudah_punya_teacher_aktif_409_data_tidak_berubah(): void
    {
        $w = $this->world();
        $a1 = $this->fxTeacherAssignment($w['t1'], $w['r1']);
        $this->fxTeacherAssignment($w['t2'], $w['r2']);

        $this->patchAssignment($w['hm'], $a1, ['rombel_id' => $w['r2']->id])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'rombel_already_has_active_teacher');

        $this->assertSame($w['r1']->id, $a1->fresh()->rombel_id);
    }

    /** @test */
    public function f18_patch_ke_rombel_yang_sama_dengan_miliknya_sendiri_bukan_konflik_dan_pindah_ke_rombel_kosong_200(): void
    {
        $w = $this->world();
        $a1 = $this->fxTeacherAssignment($w['t1'], $w['r1']);

        $this->patchAssignment($w['hm'], $a1, ['rombel_id' => $w['r1']->id])->assertStatus(200);
        $this->patchAssignment($w['hm'], $a1, ['rombel_id' => $w['r2']->id])->assertStatus(200)->assertJsonPath('data.rombel_id', $w['r2']->id);
    }

    /** @test */
    public function f18_index_ada_di_skema(): void
    {
        $this->assertNotEmpty(DB::select(
            "SELECT 1 FROM pg_indexes WHERE tablename = 'teacher_class_assignments' AND indexname = 'uq_tca_one_active_per_rombel_year'"
        ));
    }

    // ---------- F-17 ----------

    /** @test */
    public function f17_patch_assignment_inactive_ditolak_409_dan_data_tidak_berubah(): void
    {
        $w = $this->world();
        $a1 = $this->fxTeacherAssignment($w['t1'], $w['r1']);
        $a1->update(['is_active' => false]); // histori

        $this->patchAssignment($w['hm'], $a1, ['rombel_id' => $w['r2']->id])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'assignment_inactive_readonly');

        $this->assertSame($w['r1']->id, $a1->fresh()->rombel_id);
        $this->assertFalse($a1->fresh()->is_active);
    }

    /** @test */
    public function f17_assignment_aktif_tetap_bisa_di_patch_dan_post_baru_setelah_histori_berhasil(): void
    {
        $w = $this->world();
        $a1 = $this->fxTeacherAssignment($w['t1'], $w['r1']);
        $this->patchAssignment($w['hm'], $a1, ['rombel_id' => $w['r2']->id])->assertStatus(200);

        $this->apiPost("/api/v1/teacher-class-assignments/{$a1->id}/deactivate", $this->fxToken($w['hm']))->assertStatus(200);

        // Teacher yang sama, setelah yang lama inactive: assignment baru berhasil.
        $this->postAssignment($w['hm'], $w['t1'], $w['r1'])->assertStatus(201);
    }

    /** @test */
    public function f17_patch_assignment_inactive_sekolah_lain_tetap_403_scope_menang_atas_status(): void
    {
        $w = $this->world();
        $schoolB = $this->fxSchool('SD Dua');
        $yearB = AcademicYear::create([
            'school_id' => $schoolB->id, 'name' => '2026/2027', 'start_date' => '2026-07-01',
            'end_date' => '2027-06-30', 'is_active' => true,
        ]);
        $rombelB = $this->fxRombel($schoolB, $yearB, 'VII-A');
        $assignmentB = $this->fxTeacherAssignment($this->fxTeacher($schoolB, '2101')->teacher, $rombelB);
        $assignmentB->update(['is_active' => false]);

        $this->patchAssignment($w['hm'], $assignmentB, ['rombel_id' => $rombelB->id])->assertStatus(403);
    }
}
