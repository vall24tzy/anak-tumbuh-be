<?php

namespace Tests\Feature\Dashboard;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolFixtures;
use Tests\TestCase;

// STAGE 0-C - menutup sebagian TEST GAP F-42: kontrol akses semua endpoint
// dashboard + isolasi lintas-sekolah `dashboard/headmaster`. SoT: API Contract
// v1.2 §25 (tiap dashboard untuk role-nya sendiri); Authorization Matrix #56.
//
// SENGAJA TIDAK ADA di file ini (bergantung pada finding OPEN, ditulis bersama
// fix-nya): `dashboard/student` termasuk `gamification_level` (F-40) dan
// `dashboard/teacher` incl. atribusi student_count inactive/graduated (F-41).
// Yang ada di sini hanya sisi DENIAL/401 untuk keduanya, karena penolakan role
// terjadi di middleware sebelum logika dashboard dijalankan.
//
// STATUS EKSEKUSI: dijalankan di PHP + PostgreSQL; hasil terkini lihat docs/testing/BASELINE-STAGE-0C.md

class DashboardAccessTest extends TestCase
{
    use RefreshDatabase, CreatesSchoolFixtures;

    private const ENDPOINTS = [
        'student'     => '/api/v1/dashboard/student',
        'teacher'     => '/api/v1/dashboard/teacher',
        'headmaster'  => '/api/v1/dashboard/headmaster',
        'super_admin' => '/api/v1/dashboard/super-admin',
    ];

    /** @test */
    public function semua_dashboard_tanpa_token_401(): void
    {
        foreach (self::ENDPOINTS as $uri) {
            $this->apiGet($uri)
                ->assertStatus(401)
                ->assertJsonPath('error.code', 'unauthorized');
        }
    }

    /** @test */
    public function setiap_role_ditolak_403_di_dashboard_role_lain(): void
    {
        $school = $this->fxSchool();

        $actors = [
            'super_admin' => $this->fxSuperAdmin('sa_dash'),
            'headmaster'  => $this->fxHeadmaster($school, '1001'),
            'teacher'     => $this->fxTeacher($school, '2001'),
            'student'     => $this->fxStudent($school, '0051110001'),
        ];

        foreach ($actors as $role => $user) {
            $token = $this->fxToken($user);

            foreach (self::ENDPOINTS as $endpointRole => $uri) {
                if ($endpointRole === $role) {
                    continue; // sisi 200 role sendiri dicakup test dashboard masing-masing / stage F-40, F-41
                }

                $this->apiGet($uri, $token)
                    ->assertStatus(403)
                    ->assertJsonPath('error.code', 'forbidden');
            }
        }
    }

    /** @test */
    public function dashboard_headmaster_hanya_menampilkan_rombel_sekolah_sendiri(): void
    {
        $schoolA = $this->fxSchool('SD Satu');
        $schoolB = $this->fxSchool('SD Dua');

        $rombelA = $this->fxRombel($schoolA, $this->fxAcademicYear($schoolA), 'VII-A');
        $rombelB = $this->fxRombel($schoolB, $this->fxAcademicYear($schoolB), 'VII-B');

        $this->fxStudentAssignment($this->fxStudent($schoolA, '0051110001')->student, $rombelA);
        $this->fxStudentAssignment($this->fxStudent($schoolA, '0051110002')->student, $rombelA);
        $this->fxStudentAssignment($this->fxStudent($schoolB, '0051110003')->student, $rombelB);

        $hmA = $this->fxHeadmaster($schoolA, '1001');
        $hmB = $this->fxHeadmaster($schoolB, '1002');

        $responseA = $this->apiGet(self::ENDPOINTS['headmaster'], $this->fxToken($hmA))->assertStatus(200);
        $responseB = $this->apiGet(self::ENDPOINTS['headmaster'], $this->fxToken($hmB))->assertStatus(200);

        $this->assertSame([$rombelA->id], collect($responseA->json('data.rombels'))->pluck('id')->all());
        $this->assertEquals(2, $responseA->json('data.rombels.0.student_count'));

        $this->assertSame([$rombelB->id], collect($responseB->json('data.rombels'))->pluck('id')->all());
        $this->assertEquals(1, $responseB->json('data.rombels.0.student_count'));
    }
}
