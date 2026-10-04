<?php

namespace Tests\Feature\Dashboard;

use App\Models\Rombel;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolFixtures;
use Tests\TestCase;

// Stage 4A / F-41 (Matrix v3.1, keputusan FINAL): atribusi student_count.inactive/graduated di
// dashboard/teacher mengikuti assignment TERAKHIR Student pada Academic Year itu. Student yang pindah
// Rombel A -> B lalu inactive/graduated di B dihitung di B, bukan di A. Lifecycle langsung dihitung di
// Rombel tempat assignment berakhir. Metrik "active" memakai current active assignment.
//
// STATUS EKSEKUSI: ditulis di sandbox tanpa PHP/PostgreSQL; hasil run lihat laporan patch.

class DashboardTeacherAttributionTest extends TestCase
{
    use RefreshDatabase, CreatesSchoolFixtures;

    protected \App\Models\School $school;
    protected Rombel $a;
    protected Rombel $b;
    protected User $teacherA;
    protected User $teacherB;
    protected int $nisn = 51110000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = $this->fxSchool();
        $year = $this->fxAcademicYear($this->school);
        $this->a = $this->fxRombel($this->school, $year, 'VII-A');
        $this->b = $this->fxRombel($this->school, $year, 'VII-B');
        $this->teacherA = $this->fxTeacher($this->school, '2001');
        $this->teacherB = $this->fxTeacher($this->school, '2002');
        $this->fxTeacherAssignment($this->teacherA->teacher, $this->a);
        $this->fxTeacherAssignment($this->teacherB->teacher, $this->b);
    }

    protected function newStudent(): Student
    {
        return $this->fxStudent($this->school, (string) ++$this->nisn)->student;
    }

    /** Student berada di $rombel lalu assignment-nya diakhiri (urutan pembuatan = urutan id). */
    protected function endAssignment(Student $student, Rombel $rombel): void
    {
        $this->fxStudentAssignment($student, $rombel)->update(['status' => 'ended']);
    }

    protected function counts(User $teacher): array
    {
        return $this->apiGet('/api/v1/dashboard/teacher', $this->fxToken($teacher))
            ->assertStatus(200)
            ->json('data.student_count');
    }

    /** @test */
    public function f41_student_pindah_a_ke_b_lalu_graduated_di_b_hanya_dihitung_di_b(): void
    {
        $s = $this->newStudent();
        $this->endAssignment($s, $this->a);  // pindah: A berakhir karena transfer
        $this->endAssignment($s, $this->b);  // lifecycle: B berakhir karena lulus
        $s->update(['status' => 'graduated']);

        $this->assertSame(['active' => 0, 'inactive' => 0, 'graduated' => 0], $this->counts($this->teacherA));
        $this->assertSame(['active' => 0, 'inactive' => 0, 'graduated' => 1], $this->counts($this->teacherB));
    }

    /** @test */
    public function f41_student_pindah_a_ke_b_lalu_inactive_di_b_hanya_dihitung_di_b(): void
    {
        $s = $this->newStudent();
        $this->endAssignment($s, $this->a);
        $this->endAssignment($s, $this->b);
        $s->update(['status' => 'inactive']);

        $this->assertSame(0, $this->counts($this->teacherA)['inactive']);
        $this->assertSame(1, $this->counts($this->teacherB)['inactive']);
    }

    /** @test */
    public function f41_lifecycle_langsung_di_rombel_a_dihitung_di_a(): void
    {
        $inactive = $this->newStudent();
        $this->endAssignment($inactive, $this->a);
        $inactive->update(['status' => 'inactive']);

        $graduated = $this->newStudent();
        $this->endAssignment($graduated, $this->a);
        $graduated->update(['status' => 'graduated']);

        $this->assertSame(['active' => 0, 'inactive' => 1, 'graduated' => 1], $this->counts($this->teacherA));
        $this->assertSame(['active' => 0, 'inactive' => 0, 'graduated' => 0], $this->counts($this->teacherB));
    }

    /** @test */
    public function f41_student_pindah_a_ke_b_dan_masih_aktif_dihitung_active_di_b_bukan_di_a(): void
    {
        $s = $this->newStudent();
        $this->endAssignment($s, $this->a);
        $this->fxStudentAssignment($s, $this->b); // aktif di B

        $this->assertSame(['active' => 0, 'inactive' => 0, 'graduated' => 0], $this->counts($this->teacherA));
        $this->assertSame(['active' => 1, 'inactive' => 0, 'graduated' => 0], $this->counts($this->teacherB));
    }

    /** @test */
    public function f41_campuran_semua_skenario_dihitung_per_rombel_dengan_benar(): void
    {
        $active = $this->newStudent();
        $this->fxStudentAssignment($active, $this->a);

        $direct = $this->newStudent();            // inactive langsung di A
        $this->endAssignment($direct, $this->a);
        $direct->update(['status' => 'inactive']);

        $moved = $this->newStudent();             // A -> B lalu graduated di B
        $this->endAssignment($moved, $this->a);
        $this->endAssignment($moved, $this->b);
        $moved->update(['status' => 'graduated']);

        $this->assertSame(['active' => 1, 'inactive' => 1, 'graduated' => 0], $this->counts($this->teacherA));
        $this->assertSame(['active' => 0, 'inactive' => 0, 'graduated' => 1], $this->counts($this->teacherB));
    }
}
