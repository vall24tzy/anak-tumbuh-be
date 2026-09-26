<?php

namespace Tests\Feature\School;

use App\Models\AcademicYear;
use App\Models\Headmaster;
use App\Models\Rombel;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Regresi bug: PATCH /rombels/{id} dan DELETE /rombels/{id} mengembalikan 404
// untuk Rombel di sekolah lain, padahal API Contract v1.2 §14 eksplisit minta
// 403 ("403 — cross-school"). Root cause sama persis dengan
// tests/Feature/School/TeacherResetPasswordScopeTest.php: update()/destroy()
// reuse show() yang filter school_id SEBELUM find() -> "tidak ada" dan "ada
// tapi beda sekolah" jadi tidak bisa dibedakan, keduanya jatuh ke 404.
// Fix: RombelService::findRombelOrFail() (fetch global) + cek school
// terpisah, lempar 403 kalau mismatch. show()/index()/store() SENGAJA tidak
// diubah (GET tetap 404 untuk keduanya, sesuai §26).
//
// Setup pakai Model::create() berantai, konsisten dengan
// tests/Feature/School/TeacherResetPasswordScopeTest.php dan
// tests/Feature/Student/StudentLifecycleTest.php — repo ini belum punya
// factory untuk chain School/AcademicYear/Rombel/Headmaster.
//
// STATUS EKSEKUSI: belum dijalankan di sandbox ini (tidak ada Postgres live;
// skema pakai raw CHECK constraint & partial unique index Postgres,
// DB_CONNECTION=sqlite di phpunit.xml sengaja di-comment).
// Jalankan manual: php artisan test --filter=RombelWriteScopeTest

class RombelWriteScopeTest extends TestCase
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

    protected function createAcademicYear(School $school): AcademicYear
    {
        return AcademicYear::create([
            'school_id'  => $school->id,
            'name'       => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date'   => '2027-06-30',
            'is_active'  => true,
        ]);
    }

    protected function createRombel(School $school, AcademicYear $academicYear, string $name = 'VII-A'): Rombel
    {
        return Rombel::create([
            'school_id'        => $school->id,
            'academic_year_id' => $academicYear->id,
            'name'             => $name,
            'grade_level'      => 7,
        ]);
    }

    // ---- PATCH /rombels/{id} ----

    /** @test */
    public function headmaster_update_rombel_sekolah_sendiri_berhasil_200(): void
    {
        $school = School::create(['name' => 'SD Satu']);
        $academicYear = $this->createAcademicYear($school);
        $rombel = $this->createRombel($school, $academicYear);
        $hmUser = $this->createHeadmaster($school, '3001');

        $token = auth('api')->login($hmUser);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/rombels/{$rombel->id}", [
                'name' => 'VII-A Revisi',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'VII-A Revisi');
    }

    /**
     * Rombel ADA di database (bukan id ngasal), tapi di sekolah LAIN dari
     * Headmaster yang meng-update. Harus 403, bukan 404.
     *
     * @test
     */
    public function headmaster_update_rombel_sekolah_lain_ditolak_403_bukan_404(): void
    {
        $schoolA = School::create(['name' => 'SD Satu']);
        $schoolB = School::create(['name' => 'SD Dua']);

        $academicYearB = $this->createAcademicYear($schoolB);
        $rombelB = $this->createRombel($schoolB, $academicYearB);

        $hmUserA = $this->createHeadmaster($schoolA, '3002');

        $token = auth('api')->login($hmUserA);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/rombels/{$rombelB->id}", [
                'name' => 'Coba Ubah',
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function headmaster_update_rombel_id_tidak_ada_tetap_404(): void
    {
        $school = School::create(['name' => 'SD Satu']);
        $hmUser = $this->createHeadmaster($school, '3003');

        $token = auth('api')->login($hmUser);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/rombels/999999', [
                'name' => 'Coba Ubah',
            ]);

        $response->assertStatus(404);
    }

    // ---- DELETE /rombels/{id} ----

    /** @test */
    public function headmaster_delete_rombel_sekolah_sendiri_berhasil_200(): void
    {
        $school = School::create(['name' => 'SD Satu']);
        $academicYear = $this->createAcademicYear($school);
        // Sengaja TIDAK dikaitkan ke TeacherClassAssignment apapun, supaya
        // delete tidak kena 409 foreign_key_violation (di luar scope test ini).
        $rombel = $this->createRombel($school, $academicYear);
        $hmUser = $this->createHeadmaster($school, '3004');

        $token = auth('api')->login($hmUser);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/rombels/{$rombel->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('rombels', ['id' => $rombel->id]);
    }

    /**
     * Rombel ADA di database, tapi di sekolah LAIN dari Headmaster yang
     * menghapus. Harus 403, bukan 404.
     *
     * @test
     */
    public function headmaster_delete_rombel_sekolah_lain_ditolak_403_bukan_404(): void
    {
        $schoolA = School::create(['name' => 'SD Satu']);
        $schoolB = School::create(['name' => 'SD Dua']);

        $academicYearB = $this->createAcademicYear($schoolB);
        $rombelB = $this->createRombel($schoolB, $academicYearB);

        $hmUserA = $this->createHeadmaster($schoolA, '3005');

        $token = auth('api')->login($hmUserA);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/rombels/{$rombelB->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('rombels', ['id' => $rombelB->id]); // tidak ikut terhapus
    }

    /** @test */
    public function headmaster_delete_rombel_id_tidak_ada_tetap_404(): void
    {
        $school = School::create(['name' => 'SD Satu']);
        $hmUser = $this->createHeadmaster($school, '3006');

        $token = auth('api')->login($hmUser);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/v1/rombels/999999');

        $response->assertStatus(404);
    }
}
