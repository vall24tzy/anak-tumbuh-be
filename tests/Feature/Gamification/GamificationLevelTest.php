<?php

namespace Tests\Feature\Gamification;

use App\Services\Gamification\LevelService;
use Database\Seeders\GamificationLevelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesGamificationFixtures;
use Tests\TestCase;

// LEVEL-RANKING-001 — API Contract v1.2 §23, Matrix #38.
// STATUS EKSEKUSI: dijalankan di PHP + PostgreSQL pada baseline Stage 0-C: full suite 142 passed, 531 assertions
// (lihat docs/testing/BASELINE-STAGE-0C.md). Ulangi: php artisan test --filter=GamificationLevelTest

class GamificationLevelTest extends TestCase
{
    use RefreshDatabase, CreatesGamificationFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GamificationLevelSeeder::class);
    }

    /** @test */
    public function seeder_mengisi_101_baris(): void
    {
        $this->assertSame(101, \App\Models\GamificationLevel::count());
    }

    /** @test */
    public function batas_threshold_dihitung_benar(): void
    {
        $svc = app(LevelService::class);

        $cases = [
            0 => 0, 49 => 0, 50 => 1, 95 => 1, 99 => 1, 100 => 2, 199 => 2, 200 => 3,
            399 => 3, 400 => 4, 756 => 4, 757 => 5,
            750591 => 99, 750592 => 100, 1000000 => 100,
        ];

        foreach ($cases as $poin => $expected) {
            $this->assertSame($expected, $svc->levelForPoin($poin), "poin {$poin}");
        }
    }

    /** @test */
    public function level_tidak_disimpan_sebagai_kolom_dan_tidak_ada_config_ranking(): void
    {
        $this->assertFalse(Schema::hasColumn('students', 'gamification_level'));
        $this->assertFalse(Schema::hasColumn('students', 'level'));
        $this->assertFalse(Schema::hasColumn('students', 'classroom'));
        $this->assertFalse(Schema::hasColumn('schools', 'ranking_class_enabled'));
        $this->assertFalse(Schema::hasColumn('schools', 'ranking_grade_enabled'));
    }

    /** @test */
    public function student_me_mengembalikan_own_poin_dan_level(): void
    {
        [$school, $year] = $this->makeSchool();
        $rombel = $this->makeRombel($school, $year, 'VII-A', 7);
        $student = $this->makeStudent($school, $year, $rombel, 100);

        $response = $this->withHeaders($this->authHeader($student->user))->getJson('/api/v1/gamification/me');

        $response->assertStatus(200);
        $this->assertSame($student->id, $response->json('data.student_id'));
        $this->assertSame(100, $response->json('data.poin'));
        $this->assertSame(2, $response->json('data.gamification_level'));
    }

    /** @test */
    public function student_baru_poin_nol_level_nol(): void
    {
        [$school, $year] = $this->makeSchool();
        $rombel = $this->makeRombel($school, $year, 'VII-A', 7);
        $student = $this->makeStudent($school, $year, $rombel, 0);

        $response = $this->withHeaders($this->authHeader($student->user))->getJson('/api/v1/gamification/me');

        $this->assertSame(0, $response->json('data.gamification_level'));
    }

    /** @test */
    public function me_teacher_ditolak_403(): void
    {
        [$school, $year] = $this->makeSchool();
        $rombel = $this->makeRombel($school, $year, 'VII-A', 7);
        $teacher = $this->makeTeacher($school, $year, $rombel);

        $this->withHeaders($this->authHeader($teacher))->getJson('/api/v1/gamification/me')->assertStatus(403);
    }

    /** @test */
    public function me_headmaster_ditolak_403(): void
    {
        [$school] = $this->makeSchool();
        $hm = $this->makeHeadmaster($school);

        $this->withHeaders($this->authHeader($hm))->getJson('/api/v1/gamification/me')->assertStatus(403);
    }

    /** @test */
    public function me_super_admin_ditolak_403(): void
    {
        $sa = $this->makeSuperAdmin();

        $this->withHeaders($this->authHeader($sa))->getJson('/api/v1/gamification/me')->assertStatus(403);
    }

    /** @test */
    public function students_id_gamification_sesuai_scope_tiap_role(): void
    {
        [$school, $year] = $this->makeSchool();
        $rombelA = $this->makeRombel($school, $year, 'VII-A', 7);
        $rombelB = $this->makeRombel($school, $year, 'VII-B', 7);
        $student = $this->makeStudent($school, $year, $rombelA, 400);

        $teacherA = $this->makeTeacher($school, $year, $rombelA);
        $teacherB = $this->makeTeacher($school, $year, $rombelB);
        $hmOwn = $this->makeHeadmaster($school);
        [$otherSchool] = $this->makeSchool('SD Lain');
        $hmOther = $this->makeHeadmaster($otherSchool);
        $sa = $this->makeSuperAdmin();

        $url = "/api/v1/students/{$student->id}/gamification";

        foreach ([$sa, $hmOwn, $teacherA] as $allowed) {
            $r = $this->withHeaders($this->authHeader($allowed))->getJson($url);
            $r->assertStatus(200);
            $this->assertSame(4, $r->json('data.gamification_level'));
        }

        // Di luar scope -> 404 (GET-by-id, API Contract §26), bukan 403.
        $this->withHeaders($this->authHeader($teacherB))->getJson($url)->assertStatus(404);
        $this->withHeaders($this->authHeader($hmOther))->getJson($url)->assertStatus(404);

        // Student role tidak punya akses endpoint ini.
        $this->withHeaders($this->authHeader($student->user))->getJson($url)->assertStatus(403);
    }

    /** @test */
    public function tidak_ada_endpoint_write_untuk_level_atau_threshold(): void
    {
        [$school, $year] = $this->makeSchool();
        $sa = $this->makeSuperAdmin();
        $headers = $this->authHeader($sa);

        $this->withHeaders($headers)->postJson('/api/v1/gamification-levels', [])->assertStatus(404);
        $this->withHeaders($headers)->patchJson('/api/v1/gamification-levels/1', [])->assertStatus(404);
        $this->withHeaders($headers)->deleteJson('/api/v1/gamification-levels/1')->assertStatus(404);
        $this->withHeaders($headers)->postJson('/api/v1/gamification/apply-inisiatif-mandiri', [])->assertStatus(404);
    }
}
