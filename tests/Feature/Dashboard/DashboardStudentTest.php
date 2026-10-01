<?php

namespace Tests\Feature\Dashboard;

use App\Models\Student;
use App\Models\StudentClassAssignment;
use App\Models\Teacher;
use Database\Seeders\GamificationLevelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesGamificationFixtures;
use Tests\TestCase;

// F-40 - GET /dashboard/student memuat `gamification_level` (integer) yang sama dengan
// GET /gamification/me untuk Student yang sama. SoT: API Contract v1.2 §25 (contoh response,
// urutan field wali_kelas, habit_summary_today, poin, gamification_level) dan §23; DB Design §5.14
// (level dihitung real-time dari students.poin terhadap gamification_levels, tanpa kolom level).
//
// Setup: RefreshDatabase hanya menjalankan migration, bukan seeder, sedangkan level dibaca dari
// tabel gamification_levels. Tanpa baris threshold, levelForPoin() selalu 0 dan test "poin 50 ->
// level 1" tidak berarti. Maka setUp menjalankan GamificationLevelSeeder (sumber 101 baris yang
// sama dengan GamificationLevelTest/RankingTest, idempoten), bukan menyalin angka threshold ke test
// dan bukan seluruh DatabaseSeeder.
//
// Di luar scope: isi dashboard/teacher (F-41), urutan wali_kelas bila ada >1 Teacher aktif (F-18),
// dan test akses/401/403 (sudah dicakup DashboardAccessTest, tidak diubah).
//
// STATUS EKSEKUSI: dijalankan di PHP + PostgreSQL; hasil terkini lihat docs/testing/BASELINE-STAGE-0C.md

class DashboardStudentTest extends TestCase
{
    use RefreshDatabase, CreatesGamificationFixtures;

    private const URI = '/api/v1/dashboard/student';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GamificationLevelSeeder::class);
    }

    /** Sekolah + AY aktif + rombel; mengembalikan [school, year, rombel]. */
    protected function world(): array
    {
        [$school, $year] = $this->makeSchool();

        return [$school, $year, $this->makeRombel($school, $year, 'VII-A', 7)];
    }

    protected function dashboard(Student $student)
    {
        return $this->withHeaders($this->authHeader($student->user))->getJson(self::URI);
    }

    protected function gamificationMe(Student $student)
    {
        return $this->withHeaders($this->authHeader($student->user))->getJson('/api/v1/gamification/me');
    }

    // ---------- nilai level ----------

    /** @test */
    public function student_poin_nol_mendapat_gamification_level_nol(): void
    {
        [$school, $year, $rombel] = $this->world();
        $student = $this->makeStudent($school, $year, $rombel, 0);

        $response = $this->dashboard($student)->assertStatus(200);

        $this->assertSame(0, $response->json('data.poin'));
        $this->assertSame(0, $response->json('data.gamification_level'));
    }

    /** @test */
    public function batas_threshold_level_di_dashboard_student_benar(): void
    {
        [$school, $year, $rombel] = $this->world();

        // poin => level yang diharapkan (threshold: L1=50, L2=100, L3=200, L4=400, L5=757, L100=750592)
        $cases = [49 => 0, 50 => 1, 99 => 1, 100 => 2, 756 => 4, 757 => 5, 750591 => 99, 750592 => 100];

        foreach ($cases as $poin => $expected) {
            $student = $this->makeStudent($school, $year, $rombel, $poin);

            $response = $this->dashboard($student)->assertStatus(200);

            $this->assertSame($poin, $response->json('data.poin'), "poin {$poin}");
            $this->assertSame($expected, $response->json('data.gamification_level'), "level untuk poin {$poin}");
        }
    }

    /** @test */
    public function gamification_level_sama_dengan_gamification_me_untuk_student_yang_sama(): void
    {
        [$school, $year, $rombel] = $this->world();

        foreach ([0, 49, 50, 340, 757, 2000] as $poin) {
            $student = $this->makeStudent($school, $year, $rombel, $poin);

            $dashboard = $this->dashboard($student)->assertStatus(200);
            $me = $this->gamificationMe($student)->assertStatus(200);

            $this->assertIsInt($dashboard->json('data.gamification_level'), "poin {$poin}");
            $this->assertSame($me->json('data.gamification_level'), $dashboard->json('data.gamification_level'), "level poin {$poin}");
            $this->assertSame($me->json('data.poin'), $dashboard->json('data.poin'), "poin {$poin}");
        }
    }

    /** @test */
    public function level_dihitung_real_time_dan_tidak_disimpan_sebagai_kolom_students(): void
    {
        [$school, $year, $rombel] = $this->world();
        $student = $this->makeStudent($school, $year, $rombel, 49);

        $this->assertSame(0, $this->dashboard($student)->json('data.gamification_level'));

        Student::where('id', $student->id)->update(['poin' => 50]); // hanya poin yang berubah

        $this->assertSame(1, $this->dashboard($student)->json('data.gamification_level'));
        $this->assertFalse(Schema::hasColumn('students', 'gamification_level'));
        $this->assertFalse(Schema::hasColumn('students', 'level'));
    }

    // ---------- bentuk response (§25) ----------

    /** @test */
    public function bentuk_response_lengkap_sesuai_contract_25_dengan_urutan_field(): void
    {
        [$school, $year, $rombel] = $this->world();
        $teacherUser = $this->makeTeacher($school, $year, $rombel);
        $teacher = Teacher::where('user_id', $teacherUser->id)->firstOrFail();
        $student = $this->makeStudent($school, $year, $rombel, 200);

        $response = $this->dashboard($student)->assertStatus(200);
        $data = $response->json('data');

        $this->assertSame(['wali_kelas', 'habit_summary_today', 'poin', 'gamification_level'], array_keys($data));

        $this->assertSame(['id' => $teacher->id, 'name' => $teacher->fullname], $data['wali_kelas']);

        $this->assertSame(['submitted', 'total'], array_keys($data['habit_summary_today']));
        $this->assertIsInt($data['habit_summary_today']['submitted']);
        $this->assertIsInt($data['habit_summary_today']['total']);

        $this->assertSame(200, $data['poin']);
        $this->assertSame(3, $data['gamification_level']); // L3 = 200
    }

    /** @test */
    public function student_tanpa_assignment_tetap_berfungsi_dengan_wali_kelas_null_dan_level_tetap_ada(): void
    {
        [$school, $year, $rombel] = $this->world();
        $student = $this->makeStudent($school, $year, $rombel, 100);

        StudentClassAssignment::where('student_id', $student->id)->delete(); // tanpa placement

        $response = $this->dashboard($student)->assertStatus(200);
        $data = $response->json('data');

        $this->assertSame(['wali_kelas', 'habit_summary_today', 'poin', 'gamification_level'], array_keys($data));
        $this->assertNull($data['wali_kelas']);
        $this->assertSame(100, $data['poin']);
        $this->assertSame(2, $data['gamification_level']);
    }
}
