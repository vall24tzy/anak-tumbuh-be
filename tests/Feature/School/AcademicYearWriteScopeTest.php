<?php

namespace Tests\Feature\School;

use App\Models\AcademicYear;
use App\Models\Headmaster;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Regresi bug: PATCH /academic-years/{id} dan POST /academic-years/{id}/activate
// mengembalikan 404 untuk Academic Year di sekolah lain, padahal API Contract
// v1.2 §13 eksplisit minta 403 ("403 — cross-school access ke Academic Year
// sekolah lain (Headmaster maupun Teacher)"). Root cause sama persis dengan
// tests/Feature/School/TeacherResetPasswordScopeTest.php dan
// tests/Feature/School/RombelWriteScopeTest.php: update()/activate() reuse
// show() yang filter school_id SEBELUM find() -> "tidak ada" dan "ada tapi
// beda sekolah" jadi tidak bisa dibedakan, keduanya jatuh ke 404.
// Fix: AcademicYearService::findAcademicYearOrFail() (fetch global) + cek
// school terpisah, lempar 403 kalau mismatch. show()/index()/store() SENGAJA
// tidak diubah (GET tetap 404 untuk keduanya, sesuai §26).
//
// Setup pakai Model::create() berantai, konsisten dengan
// tests/Feature/School/RombelWriteScopeTest.php.
//
// STATUS EKSEKUSI: dijalankan di PHP + PostgreSQL; hasil terkini lihat docs/testing/BASELINE-STAGE-0C.md

class AcademicYearWriteScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function createHeadmaster(School $school, string $nip): User
    {
        $user = User::create([
            'role'     => 'headmaster',
            'username' => $nip,
            'password' => 'secret',
        ]);

        Headmaster::create([
            'user_id'   => $user->id,
            'school_id' => $school->id,
            'fullname'  => 'Kepala Sekolah ' . $nip,
            'nip'       => $nip,
            'gender'    => 'laki-laki',
            'status'    => 'active',
        ]);

        return $user;
    }

    protected function createAcademicYear(School $school, bool $isActive = false, string $name = '2026/2027'): AcademicYear
    {
        return AcademicYear::create([
            'school_id'  => $school->id,
            'name'       => $name,
            'start_date' => '2026-07-01',
            'end_date'   => '2027-06-30',
            'is_active'  => $isActive,
        ]);
    }

    // ---- PATCH /academic-years/{id} ----

    /** @test */
    public function headmaster_update_academic_year_sekolah_sendiri_berhasil_200(): void
    {
        $school = School::create(['name' => 'SD Satu']);
        $academicYear = $this->createAcademicYear($school);
        $hmUser = $this->createHeadmaster($school, '4001');

        $token = auth('api')->login($hmUser);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/academic-years/{$academicYear->id}", [
                'name' => '2026/2027 Revisi',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', '2026/2027 Revisi');
    }

    /**
     * Academic Year ADA di database (bukan id ngasal), tapi di sekolah LAIN
     * dari Headmaster yang meng-update. Harus 403, bukan 404.
     *
     * @test
     */
    public function headmaster_update_academic_year_sekolah_lain_ditolak_403_bukan_404(): void
    {
        $schoolA = School::create(['name' => 'SD Satu']);
        $schoolB = School::create(['name' => 'SD Dua']);

        $academicYearB = $this->createAcademicYear($schoolB);
        $hmUserA = $this->createHeadmaster($schoolA, '4002');

        $token = auth('api')->login($hmUserA);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/academic-years/{$academicYearB->id}", [
                'name' => 'Coba Ubah',
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function headmaster_update_academic_year_id_tidak_ada_tetap_404(): void
    {
        $school = School::create(['name' => 'SD Satu']);
        $hmUser = $this->createHeadmaster($school, '4003');

        $token = auth('api')->login($hmUser);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/academic-years/999999', [
                'name' => 'Coba Ubah',
            ]);

        $response->assertStatus(404);
    }

    // ---- POST /academic-years/{id}/activate ----

    /** @test */
    public function headmaster_activate_academic_year_sekolah_sendiri_berhasil_200(): void
    {
        $school = School::create(['name' => 'SD Satu']);
        $academicYear = $this->createAcademicYear($school, isActive: false);
        $hmUser = $this->createHeadmaster($school, '4004');

        $token = auth('api')->login($hmUser);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/academic-years/{$academicYear->id}/activate");

        $response->assertStatus(200);
        $response->assertJsonPath('data.is_active', true);
    }

    /**
     * Academic Year ADA di database, tapi di sekolah LAIN dari Headmaster
     * yang mencoba activate. Harus 403, bukan 404.
     *
     * @test
     */
    public function headmaster_activate_academic_year_sekolah_lain_ditolak_403_bukan_404(): void
    {
        $schoolA = School::create(['name' => 'SD Satu']);
        $schoolB = School::create(['name' => 'SD Dua']);

        $academicYearB = $this->createAcademicYear($schoolB, isActive: false);
        $hmUserA = $this->createHeadmaster($schoolA, '4005');

        $token = auth('api')->login($hmUserA);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/academic-years/{$academicYearB->id}/activate");

        $response->assertStatus(403);
        $this->assertDatabaseHas('academic_years', [
            'id'        => $academicYearB->id,
            'is_active' => false, // tidak ikut teraktivasi
        ]);
    }

    /** @test */
    public function headmaster_activate_academic_year_id_tidak_ada_tetap_404(): void
    {
        $school = School::create(['name' => 'SD Satu']);
        $hmUser = $this->createHeadmaster($school, '4006');

        $token = auth('api')->login($hmUser);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/academic-years/999999/activate');

        $response->assertStatus(404);
    }
}
