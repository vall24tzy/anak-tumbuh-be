<?php

namespace Tests\Feature\Student;

use App\Models\AcademicYear;
use App\Models\Rombel;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentClassAssignment;
use App\Models\Teacher;
use App\Models\TeacherClassAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// STUDENT-LIFECYCLE-001 (24 Sep 2026) — API Contract v1.2 §20, Dokumen v4.0
// §6.3, Matrix #41-43. Termasuk skenario Keputusan Tim NOTE-07
// (docs/db-gap-analysis.md): idempotency lifecycle dipisah dari authorization
// Student biasa — Student yang SUDAH di target state tetap 200 idempotent
// tanpa butuh activeClassAssignment lagi, TAPI wajib lolos school-scope
// check (bukan dibuka penuh), supaya tidak bocor lintas sekolah.
//
// Setup data test pakai Model::create() berantai (BUKAN factory) — sama
// seperti tests/Feature/Student/StudentUpdateNameFieldTest.php, repo ini
// belum punya factory untuk chain School/AcademicYear/Rombel/Teacher/dst.
//
// STATUS EKSEKUSI: test ini BELUM dijalankan di sandbox saya karena tidak ada
// Postgres live (skema pakai raw CHECK constraint & partial unique index
// Postgres; DB_CONNECTION=sqlite di phpunit.xml sengaja di-comment). Perlu
// dijalankan manual di dev env:
//   php artisan test --filter=StudentLifecycleTest

class StudentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bikin 1 School + 1 Academic Year aktif + 1 Rombel + 1 Teacher dengan
     * active assignment di rombel itu.
     *
     * @return array{0: School, 1: AcademicYear, 2: Rombel, 3: User, 4: Teacher}
     */
    protected function setUpSchoolWithTeacher(string $schoolName, string $nip): array
    {
        $school = School::create(['name' => $schoolName]);

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

        $teacherUser = User::create([
            'role'     => 'teacher',
            'username' => $nip,
            'password' => 'secret',
        ]);

        $teacher = Teacher::create([
            'user_id'   => $teacherUser->id,
            'school_id' => $school->id,
            'fullname'  => 'Bu Guru',
            'nip'       => $nip,
            'gender'    => 'perempuan',
            'status'    => 'active',
        ]);

        TeacherClassAssignment::create([
            'teacher_id'       => $teacher->id,
            'school_id'        => $school->id,
            'academic_year_id' => $academicYear->id,
            'rombel_id'        => $rombel->id,
            'is_active'        => true,
        ]);

        return [$school, $academicYear, $rombel, $teacherUser, $teacher];
    }

    protected function createStudentInRombel(School $school, Rombel $rombel, AcademicYear $academicYear, string $nisn, string $status = 'active'): Student
    {
        $studentUser = User::create([
            'role'     => 'student',
            'username' => $nisn,
            'password' => $nisn,
        ]);

        $student = Student::create([
            'user_id'   => $studentUser->id,
            'school_id' => $school->id,
            'fullname'  => 'Siswa ' . $nisn,
            'nisn'      => $nisn,
            'gender'    => 'laki-laki',
            'poin'      => 0,
            'status'    => $status,
        ]);

        StudentClassAssignment::create([
            'student_id'       => $student->id,
            'school_id'        => $school->id,
            'rombel_id'        => $rombel->id,
            'academic_year_id' => $academicYear->id,
            'status'           => $status === 'active' ? 'active' : 'ended',
        ]);

        return $student;
    }

    protected function bearer(User $user): string
    {
        return (string) auth('api')->login($user);
    }

    /** @test */
    public function inactive_oleh_teacher_pemilik_own_active_rombel_mengubah_status_dan_menutup_assignment(): void
    {
        [$school, $academicYear, $rombel, $teacherUser] = $this->setUpSchoolWithTeacher('SD A', '1001');
        $student = $this->createStudentInRombel($school, $rombel, $academicYear, '0051111111');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->bearer($teacherUser))
            ->postJson("/api/v1/students/{$student->id}/inactive");

        $response->assertStatus(200);
        $this->assertDatabaseHas('students', ['id' => $student->id, 'status' => 'inactive']);
        $this->assertDatabaseHas('student_class_assignments', [
            'student_id' => $student->id,
            'status'     => 'ended',
        ]);
    }

    /** @test */
    public function graduate_oleh_teacher_pemilik_own_active_rombel_mengubah_status_dan_identity_tetap_tersimpan(): void
    {
        [$school, $academicYear, $rombel, $teacherUser] = $this->setUpSchoolWithTeacher('SD B', '1002');
        $student = $this->createStudentInRombel($school, $rombel, $academicYear, '0052222222');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->bearer($teacherUser))
            ->postJson("/api/v1/students/{$student->id}/graduate");

        $response->assertStatus(200);
        $this->assertDatabaseHas('students', ['id' => $student->id, 'status' => 'graduated']);
        // Identity tetap ada (tidak hard delete, Matrix #12)
        $this->assertDatabaseHas('students', ['id' => $student->id, 'nisn' => '0052222222']);
    }

    /** @test */
    public function inactive_pada_student_di_luar_own_active_rombel_ditolak_403(): void
    {
        [$schoolA, $yearA, $rombelA, $teacherUserA] = $this->setUpSchoolWithTeacher('SD C', '1003');
        [$schoolB, $yearB, $rombelB] = $this->setUpSchoolWithTeacher('SD D', '1004');
        // Student milik rombel Teacher B, dicoba di-inactive-kan oleh Teacher A
        $student = $this->createStudentInRombel($schoolB, $rombelB, $yearB, '0053333333');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->bearer($teacherUserA))
            ->postJson("/api/v1/students/{$student->id}/inactive");

        $response->assertStatus(403);
        $this->assertDatabaseHas('students', ['id' => $student->id, 'status' => 'active']);
    }

    /** @test */
    public function inactive_pada_student_id_tidak_ada_dikembalikan_404(): void
    {
        [, , , $teacherUser] = $this->setUpSchoolWithTeacher('SD E', '1005');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->bearer($teacherUser))
            ->postJson('/api/v1/students/999999/inactive');

        $response->assertStatus(404);
    }

    /**
     * Keputusan Tim NOTE-07: Student yang SUDAH inactive (assignment-nya
     * sudah 'ended') dipanggil /inactive LAGI oleh Teacher lain yang SATU
     * SEKOLAH (tapi beda rombel, jadi tidak punya activeClassAssignment yang
     * cocok) -> tetap 200 idempotent, bukan 403.
     *
     * @test
     */
    public function inactive_idempotent_200_untuk_student_sudah_inactive_selama_masih_satu_sekolah(): void
    {
        [$school, $academicYear, $rombelA, , $teacherA] = $this->setUpSchoolWithTeacher('SD F', '1006');

        // Rombel B, Teacher B, sekolah SAMA dengan Teacher A
        $rombelB = Rombel::create([
            'school_id'        => $school->id,
            'academic_year_id' => $academicYear->id,
            'name'             => 'VII-B',
            'grade_level'      => 7,
        ]);
        $teacherUserB = User::create(['role' => 'teacher', 'username' => '1007', 'password' => 'secret']);
        $teacherB = Teacher::create([
            'user_id' => $teacherUserB->id, 'school_id' => $school->id,
            'fullname' => 'Pak Guru B', 'nip' => '1007', 'gender' => 'laki-laki', 'status' => 'active',
        ]);
        TeacherClassAssignment::create([
            'teacher_id' => $teacherB->id, 'school_id' => $school->id,
            'academic_year_id' => $academicYear->id, 'rombel_id' => $rombelB->id, 'is_active' => true,
        ]);

        // Student sudah 'inactive' (assignment sudah 'ended') di rombel A
        $student = $this->createStudentInRombel($school, $rombelA, $academicYear, '0054444444', 'inactive');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->bearer($teacherUserB))
            ->postJson("/api/v1/students/{$student->id}/inactive");

        $response->assertStatus(200);
        $this->assertDatabaseHas('students', ['id' => $student->id, 'status' => 'inactive']);
    }

    /**
     * Keputusan Tim NOTE-07 poin 5: idempotent path TETAP wajib school-scope
     * check — Teacher dari sekolah LAIN tidak boleh dapat 200 untuk Student
     * yang sudah inactive di sekolah lain.
     *
     * @test
     */
    public function inactive_idempotent_tetap_403_lintas_sekolah(): void
    {
        [$schoolA, , , $teacherUserA] = $this->setUpSchoolWithTeacher('SD G', '1008');
        [$schoolB, $yearB, $rombelB] = $this->setUpSchoolWithTeacher('SD H', '1009');

        // Student sudah 'inactive' di sekolah B
        $student = $this->createStudentInRombel($schoolB, $rombelB, $yearB, '0055555555', 'inactive');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->bearer($teacherUserA))
            ->postJson("/api/v1/students/{$student->id}/inactive");

        $response->assertStatus(403);
    }

    /** @test */
    public function bulk_graduate_meluluskan_semua_student_di_own_active_rombel(): void
    {
        [$school, $academicYear, $rombel, $teacherUser] = $this->setUpSchoolWithTeacher('SD I', '1010');
        $s1 = $this->createStudentInRombel($school, $rombel, $academicYear, '0056666661');
        $s2 = $this->createStudentInRombel($school, $rombel, $academicYear, '0056666662');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->bearer($teacherUser))
            ->postJson("/api/v1/rombels/{$rombel->id}/bulk-graduate");

        $response->assertStatus(200);
        $response->assertJsonPath('data.rombel_id', $rombel->id);
        $response->assertJsonPath('data.total_students', 2);
        $response->assertJsonPath('data.graduated_count', 2);

        $this->assertDatabaseHas('students', ['id' => $s1->id, 'status' => 'graduated']);
        $this->assertDatabaseHas('students', ['id' => $s2->id, 'status' => 'graduated']);
    }

    /** @test */
    public function bulk_graduate_dipanggil_ulang_idempotent_nol_tanpa_error(): void
    {
        [$school, $academicYear, $rombel, $teacherUser] = $this->setUpSchoolWithTeacher('SD J', '1011');
        $this->createStudentInRombel($school, $rombel, $academicYear, '0057777771');

        $token = $this->bearer($teacherUser);
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/rombels/{$rombel->id}/bulk-graduate");

        $second = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/rombels/{$rombel->id}/bulk-graduate");

        $second->assertStatus(200);
        $second->assertJsonPath('data.total_students', 0);
        $second->assertJsonPath('data.graduated_count', 0);
    }

    /** @test */
    public function bulk_graduate_pada_rombel_bukan_own_active_rombel_ditolak_403(): void
    {
        [, , , $teacherUserA] = $this->setUpSchoolWithTeacher('SD K', '1012');
        [, , $rombelB] = $this->setUpSchoolWithTeacher('SD L', '1013');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->bearer($teacherUserA))
            ->postJson("/api/v1/rombels/{$rombelB->id}/bulk-graduate");

        $response->assertStatus(403);
    }

    /** @test */
    public function bulk_graduate_pada_rombel_id_tidak_ada_dikembalikan_404(): void
    {
        [, , , $teacherUser] = $this->setUpSchoolWithTeacher('SD M', '1014');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->bearer($teacherUser))
            ->postJson('/api/v1/rombels/999999/bulk-graduate');

        $response->assertStatus(404);
    }
}
