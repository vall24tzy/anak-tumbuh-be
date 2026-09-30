<?php

namespace Tests\Feature\School;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolFixtures;
use Tests\TestCase;

// STAGE 0-C - menutup TEST GAP F-51: regression test GET show() lintas sekolah
// untuk Rombel, AcademicYear, TeacherAssignment. SoT: API Contract v1.2 §26
// ("Resource existence leak": GET by id di luar scope -> 404, bukan 403, agar
// tidak membocorkan keberadaan resource); Authorization Matrix #16, #19, #56.
// Write-op (PATCH/DELETE/deactivate -> 403) sudah dicakup *WriteScopeTest.
//
// Yang dikunci per resource, untuk Headmaster DAN Teacher:
//   - resource sekolah sendiri -> 200
//   - resource sekolah lain    -> 404 not_found
//   - pesan 404 lintas-sekolah IDENTIK dengan pesan id yang memang tidak ada
//     (tidak bisa dibedakan -> tidak ada kebocoran keberadaan)
//
// STATUS EKSEKUSI: belum dijalankan (tidak ada PHP/Postgres di lingkungan
// penyusun patch). Jalankan: php artisan test --filter=ShowCrossSchoolScopeTest

class ShowCrossSchoolScopeTest extends TestCase
{
    use RefreshDatabase, CreatesSchoolFixtures;

    private const MISSING_ID = 999999999;

    /** Dua sekolah terpisah; tiap sekolah punya AY aktif, rombel, teacher + assignment aktif. */
    protected function twoSchools(): array
    {
        $schoolA = $this->fxSchool('SD Satu');
        $schoolB = $this->fxSchool('SD Dua');

        $yearA = $this->fxAcademicYear($schoolA);
        $yearB = $this->fxAcademicYear($schoolB);

        $rombelA = $this->fxRombel($schoolA, $yearA, 'VII-A');
        $rombelB = $this->fxRombel($schoolB, $yearB, 'VII-B');

        $hmA = $this->fxHeadmaster($schoolA, '1001');
        $teacherA = $this->fxTeacher($schoolA, '2001');
        $teacherB = $this->fxTeacher($schoolB, '2002');

        $assignmentA = $this->fxTeacherAssignment($teacherA->teacher, $rombelA);
        $assignmentB = $this->fxTeacherAssignment($teacherB->teacher, $rombelB);

        return compact('hmA', 'teacherA', 'yearA', 'yearB', 'rombelA', 'rombelB', 'assignmentA', 'assignmentB');
    }

    /** @param callable(int):string $uri */
    protected function assertShowScope($actor, callable $uri, int $ownId, int $foreignId): void
    {
        $token = $this->fxToken($actor);

        $this->apiGet($uri($ownId), $token)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $ownId);

        $cross = $this->apiGet($uri($foreignId), $token);
        $missing = $this->apiGet($uri(self::MISSING_ID), $token);

        $cross->assertStatus(404)->assertJsonPath('error.code', 'not_found');
        $missing->assertStatus(404)->assertJsonPath('error.code', 'not_found');

        $this->assertSame(
            $missing->json('error.message'),
            $cross->json('error.message'),
            'pesan 404 lintas-sekolah harus sama dengan pesan id tidak ada (tanpa kebocoran keberadaan)'
        );
    }

    /** @test */
    public function rombel_show_sekolah_sendiri_200_lintas_sekolah_404_untuk_headmaster_dan_teacher(): void
    {
        $w = $this->twoSchools();

        foreach ([$w['hmA'], $w['teacherA']] as $actor) {
            $this->assertShowScope($actor, fn (int $id) => "/api/v1/rombels/{$id}", $w['rombelA']->id, $w['rombelB']->id);
        }
    }

    /** @test */
    public function academic_year_show_sekolah_sendiri_200_lintas_sekolah_404_untuk_headmaster_dan_teacher(): void
    {
        $w = $this->twoSchools();

        foreach ([$w['hmA'], $w['teacherA']] as $actor) {
            $this->assertShowScope($actor, fn (int $id) => "/api/v1/academic-years/{$id}", $w['yearA']->id, $w['yearB']->id);
        }
    }

    /** @test */
    public function teacher_assignment_show_sekolah_sendiri_200_lintas_sekolah_404_untuk_headmaster_dan_teacher(): void
    {
        $w = $this->twoSchools();

        foreach ([$w['hmA'], $w['teacherA']] as $actor) {
            $this->assertShowScope($actor, fn (int $id) => "/api/v1/teacher-class-assignments/{$id}", $w['assignmentA']->id, $w['assignmentB']->id);
        }
    }
}
