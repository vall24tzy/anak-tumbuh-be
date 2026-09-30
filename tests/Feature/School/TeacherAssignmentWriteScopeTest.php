<?php

namespace Tests\Feature\School;

use App\Models\AcademicYear;
use App\Models\Headmaster;
use App\Models\Rombel;
use App\Models\School;
use App\Models\Teacher;
use App\Models\TeacherClassAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Regresi bug: PATCH /teacher-class-assignments/{id} dan
// POST /teacher-class-assignments/{id}/deactivate mengembalikan 404 untuk
// assignment di sekolah lain, padahal konvensi umum write-op API Contract
// v1.2 §26 minta 403 untuk resource yang scope-nya jelas dari path. Root
// cause sama persis dengan tests/Feature/School/RombelWriteScopeTest.php dan
// tests/Feature/School/AcademicYearWriteScopeTest.php: update()/deactivate()
// reuse show() yang cek school_id/teacher_id SEBELUM melempar exception ->
// "tidak ada" dan "ada tapi beda scope" jadi tidak bisa dibedakan, keduanya
// jatuh ke 404. Fix: TeacherAssignmentService::findAssignmentOrFail() (fetch
// global) + assertAssignmentInScope() terpisah, lempar 403 kalau mismatch.
// show()/index()/store() SENGAJA tidak diubah (GET tetap 404 untuk
// keduanya, sesuai §26).
//
// Test ini fokus ke branch Headmaster (school_id mismatch), yang merupakan 3
// skenario wajib (tidak ada / cross-school / same-school). Branch Teacher
// (teacher_id mismatch) memakai assert method yang sama tapi tidak termasuk
// dalam scope regresi wajib yang diminta.
//
// Setup pakai Model::create() berantai, konsisten dengan
// tests/Feature/School/RombelWriteScopeTest.php.
//
// STATUS EKSEKUSI: dijalankan di PHP + PostgreSQL; hasil terkini lihat docs/testing/BASELINE-STAGE-0C.md

class TeacherAssignmentWriteScopeTest extends TestCase
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

    protected function createRombel(School $school, AcademicYear $academicYear, string $name): Rombel
    {
        return Rombel::create([
            'school_id'        => $school->id,
            'academic_year_id' => $academicYear->id,
            'name'             => $name,
            'grade_level'      => 7,
        ]);
    }

    protected function createTeacher(School $school, string $nip): Teacher
    {
        $user = User::create([
            'role'     => 'teacher',
            'username' => $nip,
            'password' => 'secret',
        ]);

        return Teacher::create([
            'user_id'   => $user->id,
            'school_id' => $school->id,
            'fullname'  => 'Guru ' . $nip,
            'nip'       => $nip,
            'gender'    => 'perempuan',
            'status'    => 'active',
        ]);
    }

    /**
     * Bikin 1 School + Academic Year aktif + Rombel + Teacher + active
     * assignment antara Teacher dan Rombel itu.
     */
    protected function createAssignment(School $school, AcademicYear $academicYear, Rombel $rombel, Teacher $teacher): TeacherClassAssignment
    {
        return TeacherClassAssignment::create([
            'teacher_id'       => $teacher->id,
            'school_id'        => $school->id,
            'academic_year_id' => $academicYear->id,
            'rombel_id'        => $rombel->id,
            'is_active'        => true,
        ]);
    }

    // ---- PATCH /teacher-class-assignments/{id} ----

    /** @test */
    public function headmaster_update_assignment_sekolah_sendiri_berhasil_200(): void
    {
        $school = School::create(['name' => 'SD Satu']);
        $academicYear = $this->createAcademicYear($school);
        $rombelLama = $this->createRombel($school, $academicYear, 'VII-A');
        $rombelBaru = $this->createRombel($school, $academicYear, 'VII-B');
        $teacher = $this->createTeacher($school, '5001');
        $assignment = $this->createAssignment($school, $academicYear, $rombelLama, $teacher);
        $hmUser = $this->createHeadmaster($school, '5011');

        $token = auth('api')->login($hmUser);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/teacher-class-assignments/{$assignment->id}", [
                'rombel_id' => $rombelBaru->id,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.rombel_id', $rombelBaru->id);
    }

    /**
     * Assignment ADA di database (bukan id ngasal), tapi di sekolah LAIN
     * dari Headmaster yang meng-update. Harus 403, bukan 404.
     *
     * @test
     */
    public function headmaster_update_assignment_sekolah_lain_ditolak_403_bukan_404(): void
    {
        $schoolA = School::create(['name' => 'SD Satu']);
        $schoolB = School::create(['name' => 'SD Dua']);

        $academicYearB = $this->createAcademicYear($schoolB);
        $rombelB = $this->createRombel($schoolB, $academicYearB, 'VIII-A');
        $teacherB = $this->createTeacher($schoolB, '5002');
        $assignmentB = $this->createAssignment($schoolB, $academicYearB, $rombelB, $teacherB);

        $hmUserA = $this->createHeadmaster($schoolA, '5012');

        $token = auth('api')->login($hmUserA);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/teacher-class-assignments/{$assignmentB->id}", [
                'rombel_id' => $rombelB->id,
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function headmaster_update_assignment_id_tidak_ada_tetap_404(): void
    {
        $school = School::create(['name' => 'SD Satu']);
        $academicYear = $this->createAcademicYear($school);
        $rombel = $this->createRombel($school, $academicYear, 'VII-A');
        $hmUser = $this->createHeadmaster($school, '5013');

        $token = auth('api')->login($hmUser);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/teacher-class-assignments/999999', [
                'rombel_id' => $rombel->id,
            ]);

        $response->assertStatus(404);
    }

    // ---- POST /teacher-class-assignments/{id}/deactivate ----

    /** @test */
    public function headmaster_deactivate_assignment_sekolah_sendiri_berhasil_200(): void
    {
        $school = School::create(['name' => 'SD Satu']);
        $academicYear = $this->createAcademicYear($school);
        $rombel = $this->createRombel($school, $academicYear, 'VII-A');
        $teacher = $this->createTeacher($school, '5003');
        $assignment = $this->createAssignment($school, $academicYear, $rombel, $teacher);
        $hmUser = $this->createHeadmaster($school, '5014');

        $token = auth('api')->login($hmUser);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/teacher-class-assignments/{$assignment->id}/deactivate");

        $response->assertStatus(200);
        $response->assertJsonPath('data.is_active', false);
    }

    /**
     * Assignment ADA di database, tapi di sekolah LAIN dari Headmaster yang
     * mencoba deactivate. Harus 403, bukan 404.
     *
     * @test
     */
    public function headmaster_deactivate_assignment_sekolah_lain_ditolak_403_bukan_404(): void
    {
        $schoolA = School::create(['name' => 'SD Satu']);
        $schoolB = School::create(['name' => 'SD Dua']);

        $academicYearB = $this->createAcademicYear($schoolB);
        $rombelB = $this->createRombel($schoolB, $academicYearB, 'VIII-A');
        $teacherB = $this->createTeacher($schoolB, '5004');
        $assignmentB = $this->createAssignment($schoolB, $academicYearB, $rombelB, $teacherB);

        $hmUserA = $this->createHeadmaster($schoolA, '5015');

        $token = auth('api')->login($hmUserA);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/teacher-class-assignments/{$assignmentB->id}/deactivate");

        $response->assertStatus(403);
        $this->assertDatabaseHas('teacher_class_assignments', [
            'id'        => $assignmentB->id,
            'is_active' => true, // tidak ikut ter-deactivate
        ]);
    }

    /** @test */
    public function headmaster_deactivate_assignment_id_tidak_ada_tetap_404(): void
    {
        $school = School::create(['name' => 'SD Satu']);
        $hmUser = $this->createHeadmaster($school, '5016');

        $token = auth('api')->login($hmUser);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/teacher-class-assignments/999999/deactivate');

        $response->assertStatus(404);
    }
}
