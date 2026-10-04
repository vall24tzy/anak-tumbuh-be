<?php

namespace Tests\Feature\School;

use App\Models\AcademicYear;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSchoolFixtures;
use Tests\TestCase;

// Stage 3 / Domain 1 - Academic Year.
//
// F-03: activate konkuren dua Academic Year berbeda di sekolah yang sama -> dulu 500 (pelanggaran
//       partial unique index uq_academic_years_one_active_per_school). Harus 409, tanpa 500, dan
//       hasil akhir tetap tepat satu aktif (API Contract v1.2 §13 fallback 409).
// F-04: PATCH hanya salah satu dari start_date/end_date harus dinilai terhadap NILAI TERSIMPAN,
//       bukan lolos validasi lalu melanggar CHECK chk_academic_years_period -> 500. Harus 422.
//
// Simulasi balapan: satu koneksi tidak bisa menjalankan dua request sungguhan bersamaan, jadi
// listener `updating` menyisipkan Academic Year aktif "pesaing" lewat query builder tepat
// sebelum UPDATE milik service (pola yang sama dengan test balapan F-13). Insert/update service
// lalu menabrak partial unique index, persis seperti kalau transaksi lain commit lebih dulu.
// Karena transaksi service di-rollback utuh, pesaing simulasi ikut terbuang; yang dibuktikan
// di sini adalah: status 409 (bukan 500), kode error, dan tidak ada keadaan setengah jadi.
//
// STATUS EKSEKUSI: ditulis di sandbox tanpa PHP/PostgreSQL; belum dijalankan. Hasil lihat laporan patch.

class AcademicYearIntegrityTest extends TestCase
{
    use RefreshDatabase, CreatesSchoolFixtures;

    protected function makeYear(School $school, string $name, bool $isActive = false, string $start = '2026-07-01', string $end = '2027-06-30'): AcademicYear
    {
        return AcademicYear::create([
            'school_id' => $school->id, 'name' => $name,
            'start_date' => $start, 'end_date' => $end, 'is_active' => $isActive,
        ]);
    }

    protected function patchYear(string $token, AcademicYear $year, array $body)
    {
        return $this->patchJson("/api/v1/academic-years/{$year->id}", $body, ['Authorization' => "Bearer {$token}"]);
    }

    protected function activeCount(School $school): int
    {
        return AcademicYear::where('school_id', $school->id)->where('is_active', true)->count();
    }

    // ---------- F-04: PATCH sebagian tanggal ----------

    /** @test */
    public function f04_patch_hanya_start_date_melebihi_end_date_tersimpan_422_dan_data_tidak_berubah(): void
    {
        $school = $this->fxSchool();
        $token = $this->fxToken($this->fxHeadmaster($school, '1001'));
        $year = $this->makeYear($school, '2026/2027'); // 2026-07-01 s.d. 2027-06-30

        $this->patchYear($token, $year, ['start_date' => '2027-08-01'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.details.0.field', 'start_date');

        $this->assertSame('2026-07-01', $year->fresh()->start_date->toDateString());
        $this->assertSame('2027-06-30', $year->fresh()->end_date->toDateString());
    }

    /** @test */
    public function f04_patch_start_date_sama_dengan_end_date_tersimpan_422(): void
    {
        $school = $this->fxSchool();
        $token = $this->fxToken($this->fxHeadmaster($school, '1001'));
        $year = $this->makeYear($school, '2026/2027');

        $this->patchYear($token, $year, ['start_date' => '2027-06-30'])
            ->assertStatus(422)
            ->assertJsonPath('error.details.0.field', 'start_date'); // CHECK-nya end > start (ketat)
    }

    /** @test */
    public function f04_patch_hanya_end_date_sebelum_start_date_tersimpan_422_dan_data_tidak_berubah(): void
    {
        $school = $this->fxSchool();
        $token = $this->fxToken($this->fxHeadmaster($school, '1001'));
        $year = $this->makeYear($school, '2026/2027');

        $this->patchYear($token, $year, ['end_date' => '2026-06-01'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.details.0.field', 'end_date');

        $this->assertSame('2027-06-30', $year->fresh()->end_date->toDateString());
    }

    /** @test */
    public function f04_patch_hanya_satu_tanggal_yang_valid_terhadap_nilai_tersimpan_tetap_200(): void
    {
        $school = $this->fxSchool();
        $token = $this->fxToken($this->fxHeadmaster($school, '1001'));
        $year = $this->makeYear($school, '2026/2027');

        $this->patchYear($token, $year, ['start_date' => '2026-08-01'])->assertStatus(200);
        $this->patchYear($token, $year, ['end_date' => '2027-07-15'])->assertStatus(200);

        $fresh = $year->fresh();
        $this->assertSame('2026-08-01', $fresh->start_date->toDateString());
        $this->assertSame('2027-07-15', $fresh->end_date->toDateString());
    }

    /** @test */
    public function f04_patch_normal_dua_tanggal_atau_tanpa_tanggal_tetap_jalan_seperti_biasa(): void
    {
        $school = $this->fxSchool();
        $token = $this->fxToken($this->fxHeadmaster($school, '1001'));
        $year = $this->makeYear($school, '2026/2027');

        $this->patchYear($token, $year, ['start_date' => '2027-01-01', 'end_date' => '2027-12-31'])->assertStatus(200);
        $this->patchYear($token, $year, ['name' => '2027/2028'])->assertStatus(200)->assertJsonPath('data.name', '2027/2028');

        // Dua tanggal terbalik tetap ditolak oleh rule request (perilaku lama tidak rusak).
        $this->patchYear($token, $year, ['start_date' => '2028-01-01', 'end_date' => '2027-01-01'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    /** @test */
    public function f04_patch_tanggal_tidak_valid_ke_sekolah_lain_tetap_403_bukan_422(): void
    {
        $schoolA = $this->fxSchool('SD Satu');
        $schoolB = $this->fxSchool('SD Dua');
        $token = $this->fxToken($this->fxHeadmaster($schoolA, '1001'));
        $yearB = $this->makeYear($schoolB, '2026/2027');

        $this->patchYear($token, $yearB, ['start_date' => '2030-01-01'])->assertStatus(403);
        $this->assertSame('2026-07-01', $yearB->fresh()->start_date->toDateString());
    }

    // ---------- F-03: activate konkuren ----------

    /** Menyisipkan Academic Year aktif pesaing tepat sebelum UPDATE aktivasi target milik service. */
    protected function simulateCompetingActivation(School $school, AcademicYear $target): void
    {
        AcademicYear::updating(function (AcademicYear $year) use ($school, $target) {
            if ($year->getKey() === $target->getKey() && $year->is_active === true) {
                DB::table('academic_years')->insert([
                    'school_id' => $school->id, 'name' => 'Pesaing', 'start_date' => '2026-07-01',
                    'end_date' => '2027-06-30', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
    }

    /** @test */
    public function f03_activate_konkuren_dua_tahun_berbeda_409_bukan_500_dan_tetap_satu_aktif(): void
    {
        $school = $this->fxSchool();
        $token = $this->fxToken($this->fxHeadmaster($school, '1001'));
        $lama = $this->makeYear($school, '2025/2026', true);
        $baru = $this->makeYear($school, '2026/2027');
        $this->simulateCompetingActivation($school, $baru);

        $this->apiPost("/api/v1/academic-years/{$baru->id}/activate", $token)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'academic_year_activation_conflict');

        // Transaksi yang kalah di-rollback utuh: tidak ada keadaan setengah jadi.
        $this->assertSame(1, $this->activeCount($school));
        $this->assertTrue($lama->fresh()->is_active);
        $this->assertFalse($baru->fresh()->is_active);
    }

    /** @test */
    public function f03_activate_sekuensial_dan_idempoten_tidak_berubah_oleh_fallback(): void
    {
        $school = $this->fxSchool();
        $token = $this->fxToken($this->fxHeadmaster($school, '1001'));
        $a = $this->makeYear($school, '2025/2026', true);
        $b = $this->makeYear($school, '2026/2027');

        $this->apiPost("/api/v1/academic-years/{$b->id}/activate", $token)->assertStatus(200)->assertJsonPath('data.is_active', true);
        $this->assertSame(1, $this->activeCount($school));
        $this->assertFalse($a->fresh()->is_active);

        $this->apiPost("/api/v1/academic-years/{$b->id}/activate", $token)->assertStatus(200)->assertJsonPath('data.is_active', true);
        $this->assertSame(1, $this->activeCount($school));
    }
}
