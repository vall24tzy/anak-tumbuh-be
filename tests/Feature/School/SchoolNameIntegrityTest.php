<?php

namespace Tests\Feature\School;

use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSchoolFixtures;
use Tests\TestCase;

// Stage 3 / Domain 4 - School.
//
// F-09: validasi `name` disamakan dengan kolom DB schools.name varchar(150) (dulu max:255 ->
//       151..255 karakter lolos validasi lalu gagal insert -> 500). 151+ -> 422; 150 -> diterima.
// F-11 (keputusan FINAL Matrix v3.1): schools.name UNIQUE di aplikasi DAN constraint DB. Duplikat
//       sekuensial -> 422; duplikat pada request konkuren -> 422 (bukan 500); constraint ada di skema.
// F-12 (POST /schools -> 201) SUDAH FIXED sebelumnya (7396a1a, SchoolStoreStatusTest 596eb36); tidak
//       dikerjakan ulang di sini.
//
// Catatan: sisi "Update" dari F-11/F-09 kini ada di PATCH /schools/{id} (INTEG-SCHOOL-001) dengan
// aturan name yang sama (max:150 + unique ignore id); dites di tests/Feature/School/SchoolUpdateTest.
//
// STATUS EKSEKUSI: ditulis di sandbox tanpa PHP/PostgreSQL; belum dijalankan. Hasil lihat laporan patch.

class SchoolNameIntegrityTest extends TestCase
{
    use RefreshDatabase, CreatesSchoolFixtures;

    protected ?string $saToken = null;

    /** Super Admin dibuat sekali per test (users.username UNIQUE; fxSuperAdmin() default selalu 'sa_fx'). */
    protected function postSchool(string $name)
    {
        $this->saToken ??= $this->fxToken($this->fxSuperAdmin());

        return $this->apiPost('/api/v1/schools', $this->saToken, ['name' => $name]);
    }

    // ---------- F-09 ----------

    /** @test */
    public function f09_nama_150_karakter_diterima_201(): void
    {
        $name = str_repeat('A', 150);

        $this->postSchool($name)->assertStatus(201)->assertJsonPath('data.name', $name);
        $this->assertDatabaseHas('schools', ['name' => $name]);
    }

    /** @test */
    public function f09_nama_151_dan_255_karakter_422_bukan_500_dan_tidak_tersimpan(): void
    {
        foreach ([151, 255] as $length) {
            $this->postSchool(str_repeat('B', $length))
                ->assertStatus(422)
                ->assertJsonPath('error.code', 'validation_failed')
                ->assertJsonPath('error.details.0.field', 'name');
        }

        $this->assertSame(0, School::count());
    }

    // ---------- F-11 ----------

    /** @test */
    public function f11_nama_kembar_sekuensial_422_pada_field_name_dan_hanya_satu_baris(): void
    {
        $this->postSchool('SD Unik')->assertStatus(201);

        $this->postSchool('SD Unik')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.details.0.field', 'name');

        $this->assertSame(1, School::where('name', 'SD Unik')->count());
    }

    /** @test */
    public function f11_nama_kembar_pada_request_konkuren_422_bukan_500(): void
    {
        // Pesaing disisipkan setelah validasi request lolos, tepat sebelum INSERT milik service
        // (pola test balapan F-13). INSERT service menabrak schools_name_unique.
        School::creating(function (School $school) {
            if ($school->name === 'SD Balapan') {
                DB::table('schools')->insert(['name' => 'SD Balapan', 'created_at' => now(), 'updated_at' => now()]);
            }
        });

        $this->postSchool('SD Balapan')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.details.0.field', 'name');

        // Transaksi service di-rollback utuh, pesaing simulasi ikut terbuang: tidak ada baris ganda.
        $this->assertLessThanOrEqual(1, School::where('name', 'SD Balapan')->count());
    }

    /** @test */
    public function f11_unique_constraint_ada_di_skema_dan_nama_berbeda_tetap_diterima(): void
    {
        $this->assertNotEmpty(DB::select(
            "SELECT 1 FROM pg_indexes WHERE tablename = 'schools' AND indexname = 'schools_name_unique'"
        ));

        $this->postSchool('SD Satu')->assertStatus(201);
        $this->postSchool('SD Dua')->assertStatus(201);
        $this->assertSame(2, School::count());
    }
}
