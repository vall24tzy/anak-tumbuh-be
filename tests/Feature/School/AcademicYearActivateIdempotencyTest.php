<?php

namespace Tests\Feature\School;

use App\Models\AcademicYear;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolFixtures;
use Tests\TestCase;

// F-02 - POST /academic-years/{id}/activate harus idempoten.
//
// Bug (TEST-002 request 4.10): activate pada Academic Year yang SUDAH aktif
// menonaktifkannya. Bulk query builder `->update(['is_active' => false])`
// menulis false ke baris target; lalu `$academicYear->update(['is_active' => true])`
// di-skip Eloquent karena nilai in-memory model sudah true (tidak dirty). Hasil:
// sekolah tanpa Academic Year aktif dan respons 200 berisi is_active=false.
//
// SoT: API Contract v1.2 §13 (activate = satu-satunya active per school, server
// menonaktifkan yang lama); DB Design §5.6 (partial unique index
// uq_academic_years_one_active_per_school); Matrix F-02 (AC: aktif tetap aktif
// 200 idempoten; tepat satu aktif per school setelah setiap activate; activate AY
// lain memindahkan status aktif).
//
// Di luar scope (F-03): activate konkuren dua tahun berbeda (lock / 409).
// Scope/otorisasi activate sudah dicakup AcademicYearWriteScopeTest.
//
// Catatan setup: fxAcademicYear() dari trait selalu membuat AY aktif, jadi test
// ini membuat AY langsung lewat Model::create() agar is_active bisa diatur.
//
// STATUS EKSEKUSI: dijalankan di PHP + PostgreSQL; hasil terkini lihat docs/testing/BASELINE-STAGE-0C.md

class AcademicYearActivateIdempotencyTest extends TestCase
{
    use RefreshDatabase, CreatesSchoolFixtures;

    protected function makeYear(School $school, string $name, bool $isActive): AcademicYear
    {
        return AcademicYear::create([
            'school_id'  => $school->id,
            'name'       => $name,
            'start_date' => '2026-07-01',
            'end_date'   => '2027-06-30',
            'is_active'  => $isActive,
        ]);
    }

    protected function activate(string $token, AcademicYear $year)
    {
        return $this->apiPost("/api/v1/academic-years/{$year->id}/activate", $token);
    }

    protected function activeCount(School $school): int
    {
        return AcademicYear::where('school_id', $school->id)->where('is_active', true)->count();
    }

    /** @test */
    public function activate_ay_yang_sudah_aktif_tetap_aktif_200_idempoten(): void
    {
        $school = $this->fxSchool();
        $active = $this->makeYear($school, '2026/2027', true);
        $sibling = $this->makeYear($school, '2027/2028', false);
        $token = $this->fxToken($this->fxHeadmaster($school, '1001'));

        $this->activate($token, $active)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $active->id)
            ->assertJsonPath('data.is_active', true);

        $this->assertTrue($active->fresh()->is_active, 'AY target harus tetap aktif di database');
        $this->assertFalse($sibling->fresh()->is_active, 'AY nonaktif lain tidak boleh berubah');
        $this->assertSame(1, $this->activeCount($school), 'tepat satu AY aktif per school');
    }

    /** @test */
    public function activate_berulang_pada_ay_aktif_selalu_menyisakan_satu_aktif(): void
    {
        $school = $this->fxSchool();
        $active = $this->makeYear($school, '2026/2027', true);
        $token = $this->fxToken($this->fxHeadmaster($school, '1001'));

        foreach ([1, 2, 3] as $attempt) {
            $this->activate($token, $active)
                ->assertStatus(200)
                ->assertJsonPath('data.is_active', true);

            $this->assertSame(1, $this->activeCount($school), "setelah activate ke-{$attempt}");
        }
    }

    /** @test */
    public function activate_ay_lain_memindahkan_status_aktif_bolak_balik(): void
    {
        $school = $this->fxSchool();
        $first = $this->makeYear($school, '2026/2027', true);
        $second = $this->makeYear($school, '2027/2028', false);
        $token = $this->fxToken($this->fxHeadmaster($school, '1001'));

        $this->activate($token, $second)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $second->id)
            ->assertJsonPath('data.is_active', true);

        $this->assertFalse($first->fresh()->is_active);
        $this->assertTrue($second->fresh()->is_active);
        $this->assertSame(1, $this->activeCount($school));

        $this->activate($token, $first)
            ->assertStatus(200)
            ->assertJsonPath('data.is_active', true);

        $this->assertTrue($first->fresh()->is_active);
        $this->assertFalse($second->fresh()->is_active);
        $this->assertSame(1, $this->activeCount($school));
    }

    /** @test */
    public function activate_pertama_kali_saat_belum_ada_ay_aktif_berhasil(): void
    {
        $school = $this->fxSchool();
        $year = $this->makeYear($school, '2026/2027', false);
        $token = $this->fxToken($this->fxHeadmaster($school, '1001'));

        $this->assertSame(0, $this->activeCount($school));

        $this->activate($token, $year)
            ->assertStatus(200)
            ->assertJsonPath('data.is_active', true);

        $this->assertTrue($year->fresh()->is_active);
        $this->assertSame(1, $this->activeCount($school));
    }

    /** @test */
    public function activate_di_satu_sekolah_tidak_mengubah_ay_aktif_sekolah_lain(): void
    {
        $schoolA = $this->fxSchool('SD Satu');
        $schoolB = $this->fxSchool('SD Dua');

        $activeA = $this->makeYear($schoolA, '2026/2027', true);
        $activeB = $this->makeYear($schoolB, '2026/2027', true);
        $tokenA = $this->fxToken($this->fxHeadmaster($schoolA, '1001'));

        // idempoten di sekolah A tidak boleh menyentuh sekolah B
        $this->activate($tokenA, $activeA)->assertStatus(200)->assertJsonPath('data.is_active', true);
        $this->assertTrue($activeB->fresh()->is_active);

        // pindah aktif di sekolah A juga tidak boleh menyentuh sekolah B
        $nextA = $this->makeYear($schoolA, '2027/2028', false);
        $this->activate($tokenA, $nextA)->assertStatus(200);

        $this->assertTrue($activeB->fresh()->is_active);
        $this->assertSame(1, $this->activeCount($schoolA));
        $this->assertSame(1, $this->activeCount($schoolB));
    }
}
