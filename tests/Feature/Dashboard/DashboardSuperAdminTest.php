<?php

namespace Tests\Feature\Dashboard;

use App\Models\AcademicYear;
use App\Models\Headmaster;
use App\Models\Rombel;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// REVISI DASHBOARD-002 (keputusan tim final): headmaster_count pada
// GET /api/v1/dashboard/super-admin hanya menghitung Headmaster
// status='active'. Sebelumnya Headmaster::count() ikut menghitung Headmaster
// inactive (yang sudah digantikan lewat suksesi HEADMASTER-001), sehingga
// angkanya membengkak tiap kali ada pergantian Kepala Sekolah.
//
// Scope test ini sengaja sempit: (1) perilaku baru headmaster_count,
// (2) regresi metric dashboard lain agar tetap sama (school_count, struktur
// response, otorisasi, dan GET /dashboard/headmaster).
//
// Setup pakai Model::create() berantai, konsisten dengan
// tests/Feature/School/RombelWriteScopeTest.php (repo belum punya factory).
//
// STATUS EKSEKUSI: belum dijalankan di sandbox ini (tidak ada PHP/Postgres
// live; skema pakai raw CHECK constraint & partial unique index Postgres,
// DB_CONNECTION=sqlite di phpunit.xml sengaja di-comment).
// Jalankan manual: php artisan test --filter=DashboardSuperAdminTest

class DashboardSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function createSuperAdmin(string $username = 'superadmin-test'): User
    {
        return User::create([
            'role'     => 'super_admin',
            'username' => $username,
            'password' => 'secret',
        ]);
    }

    protected function createHeadmaster(School $school, string $nip, string $status): User
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
            'status'    => $status,
        ]);

        return $user;
    }

    protected function getSuperAdminDashboard(): \Illuminate\Testing\TestResponse
    {
        $token = auth('api')->login($this->createSuperAdmin());

        return $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/dashboard/super-admin');
    }

    // ---- headmaster_count: perilaku baru (active saja) ----

    /** @test */
    public function headmaster_active_dihitung_di_headmaster_count(): void
    {
        $schoolA = School::create(['name' => 'SD Satu']);
        $schoolB = School::create(['name' => 'SD Dua']);

        $this->createHeadmaster($schoolA, '7001', 'active');
        $this->createHeadmaster($schoolB, '7002', 'active');

        $response = $this->getSuperAdminDashboard();

        $response->assertStatus(200);
        $response->assertJsonPath('data.headmaster_count', 2);
    }

    /**
     * SD Satu: 1 inactive (digantikan) + 1 active. SD Dua: cuma inactive.
     * Hanya 1 yang active -> headmaster_count = 1 (bukan 3 seperti
     * Headmaster::count() lama).
     *
     * @test
     */
    public function headmaster_inactive_tidak_dihitung_di_headmaster_count(): void
    {
        $schoolA = School::create(['name' => 'SD Satu']);
        $schoolB = School::create(['name' => 'SD Dua']);

        $this->createHeadmaster($schoolA, '7011', 'inactive'); // digantikan suksesi
        $this->createHeadmaster($schoolA, '7012', 'active');
        $this->createHeadmaster($schoolB, '7013', 'inactive');

        $response = $this->getSuperAdminDashboard();

        $response->assertStatus(200);
        $response->assertJsonPath('data.headmaster_count', 1);
    }

    /** @test */
    public function headmaster_count_nol_kalau_tidak_ada_headmaster_active(): void
    {
        $school = School::create(['name' => 'SD Satu']);
        $this->createHeadmaster($school, '7021', 'inactive');

        $response = $this->getSuperAdminDashboard();

        $response->assertStatus(200);
        $response->assertJsonPath('data.headmaster_count', 0);
    }

    // ---- regresi: metric & kontrak dashboard lain tetap sama ----

    /** @test */
    public function school_count_tetap_menghitung_semua_school_termasuk_headmaster_inactive(): void
    {
        $schoolA = School::create(['name' => 'SD Satu']);
        $schoolB = School::create(['name' => 'SD Dua']);
        School::create(['name' => 'SD Tiga']); // tanpa Headmaster sama sekali

        $this->createHeadmaster($schoolA, '7031', 'active');
        $this->createHeadmaster($schoolB, '7032', 'inactive');

        $response = $this->getSuperAdminDashboard();

        $response->assertStatus(200);
        $response->assertJsonPath('data.school_count', 3); // tidak terpengaruh filter Headmaster
    }

    /** @test */
    public function struktur_response_super_admin_tidak_berubah(): void
    {
        $response = $this->getSuperAdminDashboard();

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['school_count', 'headmaster_count']]);
        $this->assertSame(
            ['headmaster_count', 'school_count'],
            collect(array_keys($response->json('data')))->sort()->values()->all()
        );
    }

    /** @test */
    public function dashboard_super_admin_tetap_ditolak_403_untuk_role_headmaster(): void
    {
        $school = School::create(['name' => 'SD Satu']);
        $hmUser = $this->createHeadmaster($school, '7041', 'active');

        $token = auth('api')->login($hmUser);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/dashboard/super-admin');

        $response->assertStatus(403);
    }

    /**
     * Regresi GET /dashboard/headmaster: tidak tersentuh revisi ini. Headmaster
     * active melihat rombel sekolahnya (tahun ajaran aktif) dengan metric
     * per-rombel yang sama seperti sebelumnya.
     *
     * @test
     */
    public function dashboard_headmaster_tetap_mengembalikan_metric_per_rombel(): void
    {
        $school = School::create(['name' => 'SD Satu']);
        $hmUser = $this->createHeadmaster($school, '7051', 'active');

        $academicYear = AcademicYear::create([
            'school_id'  => $school->id,
            'name'       => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date'   => '2027-06-30',
            'is_active'  => true,
        ]);

        $rombel = Rombel::create([
            'school_id'        => $school->id,
            'academic_year_id' => $academicYear->id,
            'name'             => 'VII-A',
            'grade_level'      => 7,
        ]);

        $token = auth('api')->login($hmUser);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/dashboard/headmaster');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data.rombels');
        $response->assertJsonPath('data.rombels.0.id', $rombel->id);
        $response->assertJsonPath('data.rombels.0.name', 'VII-A');
        $response->assertJsonPath('data.rombels.0.grade_level', 7);
        $response->assertJsonPath('data.rombels.0.student_count', 0);
        $response->assertJsonPath('data.rombels.0.average_submitted_today', 0);
    }
}