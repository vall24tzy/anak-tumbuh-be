<?php

namespace Tests\Feature\School;

use App\Services\Dashboard\DashboardService;
use App\Services\Gamification\GamificationService;
use App\Services\School\AcademicYearService;
use App\Services\School\RombelService;
use App\Services\School\TeacherAssignmentService;
use App\Services\School\TeacherImportService;
use App\Services\School\TeacherScopeService;
use App\Services\School\TeacherService;
use App\Services\Student\StudentClassAssignmentService;
use App\Services\Student\StudentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\CreatesSchoolFixtures;
use Tests\TestCase;

// F-14 residual (Stage 2A) - scope resolver di service hanya menerima profil AKTIF.
// SoT: Matrix v3.1 F-14 (keputusan FINAL: Headmaster/Teacher inactive tidak boleh memakai
// authority aktif; data historis tetap; Super Admin tetap mengelola lifecycle), DB Design
// R1/R4/R7, pola penolakan R7 (403).
//
// REACHABLE vs TIDAK lewat HTTP: Headmaster/Teacher inactive SUDAH ditolak lebih dulu oleh
// middleware `jwt` (AuthenticateActiveSession) di setiap request, termasuk token lama
// (dikunci SessionValidityTest, tidak diubah). Karena itu resolver di bawah ini TIDAK
// terjangkau lewat jalur HTTP; lapisan ini defense-in-depth bila service dipanggil dari jalur
// lain (job, console, atau middleware yang gagal). Maka test memanggil SERVICE langsung dengan
// user inactive yang di-set ke guard (meniru ActsAsActiveBatchOwner), melewati middleware.
//
// Yang dikunci:
//   - Headmaster/Teacher inactive -> 403 di resolver; profil aktif tetap bekerja (kontrol)
//   - Teacher inactive tidak punya scope di TeacherScopeService walau assignment-nya masih aktif
//   - data historis tidak terhapus; endpoint lifecycle (suksesi Headmaster, inactive Teacher)
//     tidak diubah (tercakup SessionValidityTest)
//
// STATUS EKSEKUSI: dijalankan di PHP + PostgreSQL; hasil terkini lihat docs/testing/BASELINE-STAGE-0C.md

class InactiveProfileScopeTest extends TestCase
{
    use RefreshDatabase, CreatesSchoolFixtures;

    /** Satu sekolah dengan HM, Teacher (assignment aktif), dan Student; profil bisa di-inactive-kan. */
    protected function world(): array
    {
        $school = $this->fxSchool();
        $year = $this->fxAcademicYear($school);
        $rombel = $this->fxRombel($school, $year, 'VII-A');

        $hm = $this->fxHeadmaster($school, '1001');
        $teacher = $this->fxTeacher($school, '2001');
        $assignment = $this->fxTeacherAssignment($teacher->teacher, $rombel);

        $studentUser = $this->fxStudent($school, '0051234567');
        $this->fxStudentAssignment($studentUser->student, $rombel);

        return compact('school', 'year', 'rombel', 'hm', 'teacher', 'assignment', 'studentUser');
    }

    /** Set user ke guard TANPA melewati middleware (meniru job/console). */
    protected function actAs($user): void
    {
        auth('api')->setUser($user->fresh());
    }

    protected function assertForbidden(callable $call, string $label): void
    {
        try {
            $call();
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode(), $label);

            return;
        }

        $this->fail("{$label}: seharusnya 403 untuk profil inactive");
    }

    /** @return array<string, callable> resolver yang dipakai Headmaster (setiap callable memicu resolusi scope). */
    protected function headmasterCalls(): array
    {
        return [
            'AcademicYearService::index'          => fn () => app(AcademicYearService::class)->index(),
            'RombelService::index'                => fn () => app(RombelService::class)->index(),
            'TeacherService::index'               => fn () => app(TeacherService::class)->index(),
            'TeacherAssignmentService::index'     => fn () => app(TeacherAssignmentService::class)->index(),
            'StudentService::index'               => fn () => app(StudentService::class)->index(),
            'StudentClassAssignmentService::index' => fn () => app(StudentClassAssignmentService::class)->index(),
            'DashboardService::forHeadmaster'     => fn () => app(DashboardService::class)->forHeadmaster(),
            'TeacherImportService::commit'        => fn () => app(TeacherImportService::class)->commit('tidak-dibaca-karena-ditolak-sebelum-file'),
        ];
    }

    // ---------- Headmaster ----------

    /** @test */
    public function headmaster_inactive_ditolak_403_di_semua_resolver_scope(): void
    {
        $w = $this->world();
        $w['hm']->headmaster->update(['status' => 'inactive']);
        $this->actAs($w['hm']);

        foreach ($this->headmasterCalls() as $label => $call) {
            $this->assertForbidden($call, $label);
        }
    }

    /** @test */
    public function headmaster_aktif_tetap_bekerja_di_resolver_yang_sama(): void
    {
        $w = $this->world();
        $this->actAs($w['hm']);

        foreach (array_diff_key($this->headmasterCalls(), ['TeacherImportService::commit' => 1]) as $label => $call) {
            $call();   // tidak boleh melempar
            $this->addToAssertionCount(1);
        }

        $this->assertCount(1, app(AcademicYearService::class)->index());
        $this->assertCount(1, app(TeacherService::class)->index());
    }

    /** @test */
    public function headmaster_inactive_tidak_menghapus_data_historis(): void
    {
        $w = $this->world();
        $w['hm']->headmaster->update(['status' => 'inactive']);

        $this->assertDatabaseHas('headmasters', ['user_id' => $w['hm']->id, 'status' => 'inactive']);
        $this->assertDatabaseHas('teacher_class_assignments', ['id' => $w['assignment']->id]);
        $this->assertDatabaseHas('academic_years', ['id' => $w['year']->id]);
    }

    /** @test */
    public function gamification_headmaster_inactive_tidak_bisa_melihat_siswa_sekolahnya_404(): void
    {
        $w = $this->world();
        $studentId = $w['studentUser']->student->id;

        $this->actAs($w['hm']);
        $this->assertSame($studentId, app(GamificationService::class)->forStudent($studentId)['student_id']);

        $w['hm']->headmaster->update(['status' => 'inactive']);
        $this->actAs($w['hm']);

        try {
            app(GamificationService::class)->forStudent($studentId);
            $this->fail('Headmaster inactive seharusnya tidak dapat melihat siswa (404)');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    // ---------- Teacher ----------

    /** @test */
    public function teacher_inactive_ditolak_403_di_resolver_scope_yang_dipakai_teacher(): void
    {
        $w = $this->world();
        $w['teacher']->teacher->update(['status' => 'inactive']);
        $this->actAs($w['teacher']);

        $this->assertForbidden(fn () => app(AcademicYearService::class)->index(), 'AcademicYearService::index');
        $this->assertForbidden(fn () => app(RombelService::class)->index(), 'RombelService::index');
        $this->assertForbidden(fn () => app(TeacherAssignmentService::class)->index(), 'TeacherAssignmentService::index');
    }

    /** @test */
    public function teacher_aktif_tetap_bekerja_di_resolver_yang_sama(): void
    {
        $w = $this->world();
        $this->actAs($w['teacher']);

        $this->assertCount(1, app(AcademicYearService::class)->index());
        $this->assertCount(1, app(RombelService::class)->index());
        $this->assertCount(1, app(TeacherAssignmentService::class)->index());
    }

    /** @test */
    public function teacher_inactive_tidak_dapat_melihat_assignment_miliknya_404(): void
    {
        $w = $this->world();
        $this->actAs($w['teacher']);
        $this->assertSame($w['assignment']->id, app(TeacherAssignmentService::class)->show($w['assignment']->id)->id);

        $w['teacher']->teacher->update(['status' => 'inactive']);
        $this->actAs($w['teacher']);

        try {
            app(TeacherAssignmentService::class)->show($w['assignment']->id);
            $this->fail('Teacher inactive seharusnya tidak melihat assignment (404)');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    /** @test */
    public function teacher_scope_service_menolak_teacher_inactive_walau_assignment_masih_aktif(): void
    {
        $w = $this->world();
        $scope = app(TeacherScopeService::class);

        $this->assertSame($w['assignment']->id, $scope->activeAssignment($w['teacher']->fresh())?->id);

        // Status profil diubah TANPA menutup assignment: assignment tetap is_active=true.
        $w['teacher']->teacher->update(['status' => 'inactive']);

        $this->assertDatabaseHas('teacher_class_assignments', ['id' => $w['assignment']->id, 'is_active' => true]);
        $this->assertNull($scope->activeAssignment($w['teacher']->fresh()));
    }
}
