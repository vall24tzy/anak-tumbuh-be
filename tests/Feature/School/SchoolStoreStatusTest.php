<?php

namespace Tests\Feature\School;

use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolFixtures;
use Tests\TestCase;

// F-12 - POST /api/v1/schools (Create School) mengembalikan HTTP 201.
// SoT: Authorization Matrix #1 (Create School: Super Admin only); kode sukses 201
// diubah di SchoolController::store() (commit 7396a1a) dan sebelumnya belum punya
// test PHPUnit. Matrix v3 masih mencatat perilaku lama (200).
//
// Yang dikunci:
//   - Super Admin + data valid -> 201, body {data:{id,name,address,logo}}, baris tersimpan
//   - Headmaster / Teacher / Student -> 403 forbidden (ditolak middleware role),
//     tidak ada baris schools baru
//
// Setiap token diambil tepat sebelum request aktornya (fxToken men-set user di guard).
//
// STATUS EKSEKUSI: dijalankan di PHP + PostgreSQL; hasil terkini lihat docs/testing/BASELINE-STAGE-0C.md

class SchoolStoreStatusTest extends TestCase
{
    use RefreshDatabase, CreatesSchoolFixtures;

    private const URI = '/api/v1/schools';

    private function payload(): array
    {
        return ['name' => 'SD Baru Harapan', 'address' => 'Jl. Melati No. 1'];
    }

    /** @test */
    public function super_admin_membuat_school_valid_mendapat_201_dan_data_tersimpan(): void
    {
        $sa = $this->fxSuperAdmin();

        $response = $this->apiPost(self::URI, $this->fxToken($sa), $this->payload());

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'SD Baru Harapan')
            ->assertJsonPath('data.address', 'Jl. Melati No. 1')
            ->assertJsonPath('data.logo', null)
            ->assertJsonStructure(['data' => ['id', 'name', 'address', 'logo']]);

        $this->assertIsInt($response->json('data.id'));
        $this->assertDatabaseHas('schools', [
            'id'      => $response->json('data.id'),
            'name'    => 'SD Baru Harapan',
            'address' => 'Jl. Melati No. 1',
        ]);
    }

    /** @test */
    public function headmaster_dan_teacher_ditolak_403_dan_tidak_ada_school_baru(): void
    {
        $school = $this->fxSchool('SD Satu');
        $hm = $this->fxHeadmaster($school, '1001');
        $teacher = $this->fxTeacher($school, '2001');

        $this->assertSame(1, School::count());

        foreach ([$hm, $teacher] as $actor) {
            $this->apiPost(self::URI, $this->fxToken($actor), $this->payload())
                ->assertStatus(403)
                ->assertJsonPath('error.code', 'forbidden');
        }

        $this->assertSame(1, School::count(), 'tidak boleh ada school baru dari role non Super Admin');
        $this->assertDatabaseMissing('schools', ['name' => 'SD Baru Harapan']);
    }

    /** @test */
    public function student_ditolak_403_dan_tidak_ada_school_baru(): void
    {
        $school = $this->fxSchool('SD Satu');
        $student = $this->fxStudent($school, '0051234567');

        $this->apiPost(self::URI, $this->fxToken($student), $this->payload())
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');

        $this->assertSame(1, School::count());
        $this->assertDatabaseMissing('schools', ['name' => 'SD Baru Harapan']);
    }
}
