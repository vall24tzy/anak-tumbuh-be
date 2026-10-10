<?php

namespace Tests\Feature\Student;

use App\Models\AcademicYear;
use App\Models\Headmaster;
use App\Models\Rombel;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentClassAssignment;
use App\Models\Teacher;
use App\Models\TeacherClassAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Pagination GET /students dan GET /student-class-assignments (butir C-02
// MERGE-001). API Contract v1.2 §7: page (default 1), per_page (default 20,
// maksimum 100), meta {page, per_page, total, total_pages}. per_page > 100
// ditolak 422 validation_failed. Scope per role TIDAK berubah. Lihat
// docs/addendum/API-Contract-v1.2-Addendum-Pagination-Students.md.
//
// Setup memakai Model::create() berantai (bukan factory), sama seperti
// StudentLifecycleTest.

class StudentListPaginationTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    /** @return array{0: School, 1: AcademicYear, 2: Rombel, 3: User} */
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

        $teacherUser = User::create(['role' => 'teacher', 'username' => $nip, 'password' => 'secret']);

        $teacher = Teacher::create([
            'user_id'   => $teacherUser->id,
            'school_id' => $school->id,
            'fullname'  => 'Bu Guru ' . $nip,
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

        return [$school, $academicYear, $rombel, $teacherUser];
    }

    protected function createStudentInRombel(School $school, Rombel $rombel, AcademicYear $academicYear): Student
    {
        $nisn = '70' . str_pad((string) (++$this->seq), 8, '0', STR_PAD_LEFT);

        $user = User::create(['role' => 'student', 'username' => $nisn, 'password' => $nisn]);

        $student = Student::create([
            'user_id'   => $user->id,
            'school_id' => $school->id,
            'fullname'  => 'Siswa ' . $nisn,
            'nisn'      => $nisn,
            'gender'    => 'laki-laki',
            'poin'      => 0,
            'status'    => 'active',
        ]);

        StudentClassAssignment::create([
            'student_id'       => $student->id,
            'school_id'        => $school->id,
            'rombel_id'        => $rombel->id,
            'academic_year_id' => $academicYear->id,
            'status'           => 'active',
        ]);

        return $student;
    }

    protected function createHeadmaster(School $school, string $nip): User
    {
        $user = User::create(['role' => 'headmaster', 'username' => $nip, 'password' => 'secret']);

        Headmaster::create([
            'user_id'   => $user->id,
            'school_id' => $school->id,
            'fullname'  => 'Kepala ' . $nip,
            'nip'       => $nip,
            'gender'    => 'laki-laki',
            'status'    => 'active',
        ]);

        return $user;
    }

    protected function getAs(User $user, string $url)
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login($user))->getJson($url);
    }

    protected function seedRombelWith(int $count): array
    {
        [$school, $year, $rombel, $teacherUser] = $this->setUpSchoolWithTeacher('SD A', '1001');
        $students = [];
        for ($i = 0; $i < $count; $i++) {
            $students[] = $this->createStudentInRombel($school, $rombel, $year);
        }

        return [$school, $year, $rombel, $teacherUser, $students];
    }

    /** @test */
    public function students_teacher_default_per_page_20_dengan_meta(): void
    {
        [, , , $teacherUser] = $this->seedRombelWith(25);

        $response = $this->getAs($teacherUser, '/api/v1/students');

        $response->assertStatus(200);
        $response->assertJsonCount(20, 'data');
        $response->assertJsonPath('meta.page', 1);
        $response->assertJsonPath('meta.per_page', 20);
        $response->assertJsonPath('meta.total', 25);
        $response->assertJsonPath('meta.total_pages', 2);
    }

    /** @test */
    public function students_halaman_2_berisi_sisa_tanpa_tumpang_tindih(): void
    {
        [, , , $teacherUser] = $this->seedRombelWith(25);

        $p1 = $this->getAs($teacherUser, '/api/v1/students')->json('data');
        $p2 = $this->getAs($teacherUser, '/api/v1/students?page=2');

        $p2->assertStatus(200);
        $p2->assertJsonCount(5, 'data');
        $p2->assertJsonPath('meta.page', 2);

        $ids1 = array_column($p1, 'id');
        $ids2 = array_column($p2->json('data'), 'id');
        $this->assertEmpty(array_intersect($ids1, $ids2));
        $this->assertSame($ids1, collect($ids1)->sort()->values()->all(), 'urutan id naik');
    }

    /** @test */
    public function students_per_page_100_diterima_dan_per_page_101_ditolak_422(): void
    {
        [, , , $teacherUser] = $this->seedRombelWith(3);

        $this->getAs($teacherUser, '/api/v1/students?per_page=100')
            ->assertStatus(200)->assertJsonPath('meta.per_page', 100);

        $this->getAs($teacherUser, '/api/v1/students?per_page=101')
            ->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
    }

    /** @test */
    public function students_per_page_0_dan_page_0_ditolak_422(): void
    {
        [, , , $teacherUser] = $this->seedRombelWith(1);

        $this->getAs($teacherUser, '/api/v1/students?per_page=0')->assertStatus(422);
        $this->getAs($teacherUser, '/api/v1/students?page=0')->assertStatus(422);
    }

    /** @test */
    public function students_teacher_tanpa_assignment_aktif_200_kosong_dengan_meta(): void
    {
        $school = School::create(['name' => 'SD Z']);
        $teacherUser = User::create(['role' => 'teacher', 'username' => '9001', 'password' => 'secret']);
        Teacher::create([
            'user_id' => $teacherUser->id, 'school_id' => $school->id, 'fullname' => 'Guru Z',
            'nip' => '9001', 'gender' => 'perempuan', 'status' => 'active',
        ]);

        $response = $this->getAs($teacherUser, '/api/v1/students');

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');
        $response->assertJsonPath('meta.total', 0);
    }

    /** @test */
    public function students_scope_per_role_tidak_berubah_headmaster_sekolah_sendiri_student_diri_sendiri(): void
    {
        [$schoolA, $yearA, $rombelA, , $studentsA] = $this->seedRombelWith(3);
        [$schoolB, $yearB, $rombelB] = $this->setUpSchoolWithTeacher('SD B', '1002');
        $this->createStudentInRombel($schoolB, $rombelB, $yearB);
        $this->createStudentInRombel($schoolB, $rombelB, $yearB);

        $hmA = $this->createHeadmaster($schoolA, '5001');
        $this->getAs($hmA, '/api/v1/students')
            ->assertStatus(200)->assertJsonPath('meta.total', 3);

        $sa = User::create(['role' => 'super_admin', 'username' => 'sa-pagination', 'password' => 'secret']);
        $this->getAs($sa, '/api/v1/students?per_page=2')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.total_pages', 3);

        $this->getAs($studentsA[0]->user, '/api/v1/students')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1);
    }

    /** @test */
    public function class_assignments_teacher_default_20_dengan_meta_dan_halaman_2(): void
    {
        [, , , $teacherUser] = $this->seedRombelWith(25);

        $this->getAs($teacherUser, '/api/v1/student-class-assignments')
            ->assertStatus(200)
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.total_pages', 2);

        $this->getAs($teacherUser, '/api/v1/student-class-assignments?page=2')
            ->assertStatus(200)->assertJsonCount(5, 'data');
    }

    /** @test */
    public function class_assignments_per_page_101_ditolak_422(): void
    {
        [, , , $teacherUser] = $this->seedRombelWith(1);

        $this->getAs($teacherUser, '/api/v1/student-class-assignments?per_page=101')
            ->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
    }

    /** @test */
    public function class_assignments_scope_per_role_tidak_berubah(): void
    {
        [$schoolA, , , , $studentsA] = $this->seedRombelWith(3);
        [$schoolB, $yearB, $rombelB] = $this->setUpSchoolWithTeacher('SD B', '1002');
        $this->createStudentInRombel($schoolB, $rombelB, $yearB);

        $hmA = $this->createHeadmaster($schoolA, '5001');
        $this->getAs($hmA, '/api/v1/student-class-assignments')
            ->assertStatus(200)->assertJsonPath('meta.total', 3);

        $sa = User::create(['role' => 'super_admin', 'username' => 'sa-pagination', 'password' => 'secret']);
        $this->getAs($sa, '/api/v1/student-class-assignments?per_page=3')
            ->assertStatus(200)->assertJsonPath('meta.total', 4)->assertJsonPath('meta.total_pages', 2);

        $this->getAs($studentsA[0]->user, '/api/v1/student-class-assignments')
            ->assertStatus(200)->assertJsonPath('meta.total', 1);
    }
}
