<?php

namespace Tests\Feature\Student;

use App\Models\AcademicYear;
use App\Models\Habit;
use App\Models\Indicator;
use App\Models\Rombel;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentClassAssignment;
use App\Models\StudentHabit;
use App\Models\Teacher;
use App\Models\TeacherClassAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Addendum: Habit GET Response & Date Filter (Keputusan Tim, 25 Sep 2026).
// Cakupan: student_id di response GET (sudah ada sejak GAP GET-1, dites ulang
// di sini), filter date=YYYY-MM-DD (baru), dan negative cases (student_id/
// rombel_id TIDAK boleh jadi query filter, tidak ada PATCH/DELETE).

class StudentHabitGetFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function makeSchoolWithRombel(string $rombelName = 'X-A'): array
    {
        $school = School::create(['name' => 'Sekolah ' . $rombelName]);

        $ay = AcademicYear::create([
            'school_id'  => $school->id,
            'name'       => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date'   => '2027-06-30',
            'is_active'  => true,
        ]);

        $rombel = Rombel::create([
            'school_id'        => $school->id,
            'academic_year_id' => $ay->id,
            'name'             => $rombelName,
            'grade_level'      => 7,
        ]);

        return [$school, $ay, $rombel];
    }

    protected function makeTeacher(School $school, AcademicYear $ay, Rombel $rombel, string $username): User
    {
        $user = User::create(['role' => 'teacher', 'username' => $username, 'password' => 'secret']);

        $teacher = Teacher::create([
            'user_id'   => $user->id,
            'school_id' => $school->id,
            'fullname'  => 'Guru ' . $username,
            'nip'       => $username,
            'gender'    => 'perempuan',
            'status'    => 'active',
        ]);

        TeacherClassAssignment::create([
            'teacher_id'       => $teacher->id,
            'school_id'        => $school->id,
            'academic_year_id' => $ay->id,
            'rombel_id'        => $rombel->id,
            'is_active'        => true,
        ]);

        return $user;
    }

    protected function makeStudent(School $school, AcademicYear $ay, Rombel $rombel, string $nisn): Student
    {
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
            'academic_year_id' => $ay->id,
            'status'           => 'active',
        ]);

        return $student;
    }

    protected function makeHabitIndicator(): array
    {
        $habit = Habit::create([
            'code'                => 'ibadah',
            'name'                => 'Ibadah',
            'allows_multi_select' => false,
            'sort_order'          => 1,
        ]);

        $indicator = Indicator::create([
            'habit_id'     => $habit->id,
            'name'         => 'Sholat 5 waktu',
            'point'        => 25,
            'is_exclusive' => false,
            'sort_order'   => 1,
        ]);

        return [$habit, $indicator];
    }

    /** @test */
    public function get_habits_entries_tanpa_date_tetap_berhasil_hanya_own_active_rombel(): void
    {
        [$school, $ay, $rombel] = $this->makeSchoolWithRombel();
        $teacherUser = $this->makeTeacher($school, $ay, $rombel, 'guru1');
        $student = $this->makeStudent($school, $ay, $rombel, '0011');
        [$habit, $indicator] = $this->makeHabitIndicator();

        StudentHabit::create([
            'student_id' => $student->id, 'habit_id' => $habit->id, 'indicator_id' => $indicator->id,
            'habit_date' => '2026-09-20', 'initiative' => 'mandiri',
        ]);

        $token = auth('api')->login($teacherUser);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/habits/entries');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    /** @test */
    public function get_habits_entries_dengan_date_hanya_mengembalikan_entry_tanggal_tersebut(): void
    {
        [$school, $ay, $rombel] = $this->makeSchoolWithRombel();
        $teacherUser = $this->makeTeacher($school, $ay, $rombel, 'guru2');
        $student = $this->makeStudent($school, $ay, $rombel, '0021');
        [$habit, $indicator] = $this->makeHabitIndicator();

        StudentHabit::create([
            'student_id' => $student->id, 'habit_id' => $habit->id, 'indicator_id' => $indicator->id,
            'habit_date' => '2026-09-20', 'initiative' => 'mandiri',
        ]);
        StudentHabit::create([
            'student_id' => $student->id, 'habit_id' => $habit->id, 'indicator_id' => $indicator->id,
            'habit_date' => '2026-09-21', 'initiative' => 'disuruh',
        ]);

        $token = auth('api')->login($teacherUser);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/habits/entries?date=2026-09-20');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.habit_date', '2026-09-20');
    }

    /** @test */
    public function get_bermasyarakat_entries_dengan_date_hanya_mengembalikan_entry_tanggal_tersebut(): void
    {
        [$school, $ay, $rombel] = $this->makeSchoolWithRombel();
        $teacherUser = $this->makeTeacher($school, $ay, $rombel, 'guru3');
        $student = $this->makeStudent($school, $ay, $rombel, '0031');

        $bermasyarakat = Habit::create([
            'code' => 'bermasyarakat', 'name' => 'Bermasyarakat',
            'allows_multi_select' => true, 'sort_order' => 7,
        ]);
        $indicator = Indicator::create([
            'habit_id' => $bermasyarakat->id, 'name' => 'Gotong royong',
            'point' => null, 'is_exclusive' => false, 'sort_order' => 1,
        ]);

        $entryLama = StudentHabit::create([
            'student_id' => $student->id, 'habit_id' => $bermasyarakat->id,
            'habit_date' => '2026-09-19', 'initiative' => 'mandiri',
        ]);
        $entryLama->selectedIndicators()->create(['indicator_id' => $indicator->id]);

        $entryBaru = StudentHabit::create([
            'student_id' => $student->id, 'habit_id' => $bermasyarakat->id,
            'habit_date' => '2026-09-20', 'initiative' => 'mandiri',
        ]);
        $entryBaru->selectedIndicators()->create(['indicator_id' => $indicator->id]);

        $token = auth('api')->login($teacherUser);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/bermasyarakat/entries?date=2026-09-20');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    /** @test */
    public function student_get_hanya_melihat_own_data(): void
    {
        [$school, $ay, $rombel] = $this->makeSchoolWithRombel();
        $student1 = $this->makeStudent($school, $ay, $rombel, '0041');
        $student2 = $this->makeStudent($school, $ay, $rombel, '0042');
        [$habit, $indicator] = $this->makeHabitIndicator();

        StudentHabit::create([
            'student_id' => $student1->id, 'habit_id' => $habit->id, 'indicator_id' => $indicator->id,
            'habit_date' => '2026-09-20', 'initiative' => 'mandiri',
        ]);
        StudentHabit::create([
            'student_id' => $student2->id, 'habit_id' => $habit->id, 'indicator_id' => $indicator->id,
            'habit_date' => '2026-09-20', 'initiative' => 'mandiri',
        ]);

        $token = auth('api')->login($student1->user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/habits/entries');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.student_id', $student1->id);
    }

    /** @test */
    public function teacher_tidak_melihat_entry_student_di_rombel_lain(): void
    {
        [$schoolA, $ayA, $rombelA] = $this->makeSchoolWithRombel('X-A');
        [$schoolB, $ayB, $rombelB] = $this->makeSchoolWithRombel('X-B');

        $teacherA = $this->makeTeacher($schoolA, $ayA, $rombelA, 'guruA');
        $studentB = $this->makeStudent($schoolB, $ayB, $rombelB, '0051');
        [$habit, $indicator] = $this->makeHabitIndicator();

        StudentHabit::create([
            'student_id' => $studentB->id, 'habit_id' => $habit->id, 'indicator_id' => $indicator->id,
            'habit_date' => '2026-09-20', 'initiative' => 'mandiri',
        ]);

        $token = auth('api')->login($teacherA);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/habits/entries');

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');
    }

    /** @test */
    public function response_get_memiliki_student_id(): void
    {
        [$school, $ay, $rombel] = $this->makeSchoolWithRombel();
        $teacherUser = $this->makeTeacher($school, $ay, $rombel, 'guru6');
        $student = $this->makeStudent($school, $ay, $rombel, '0061');
        [$habit, $indicator] = $this->makeHabitIndicator();

        StudentHabit::create([
            'student_id' => $student->id, 'habit_id' => $habit->id, 'indicator_id' => $indicator->id,
            'habit_date' => '2026-09-20', 'initiative' => 'mandiri',
        ]);

        $token = auth('api')->login($teacherUser);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/habits/entries');

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.student_id', $student->id);
    }

    /** @test */
    public function detail_entry_di_luar_scope_tetap_404(): void
    {
        [$schoolA, $ayA, $rombelA] = $this->makeSchoolWithRombel('X-A');
        [$schoolB, $ayB, $rombelB] = $this->makeSchoolWithRombel('X-B');

        $teacherA = $this->makeTeacher($schoolA, $ayA, $rombelA, 'guruA2');
        $studentB = $this->makeStudent($schoolB, $ayB, $rombelB, '0071');
        [$habit, $indicator] = $this->makeHabitIndicator();

        $entry = StudentHabit::create([
            'student_id' => $studentB->id, 'habit_id' => $habit->id, 'indicator_id' => $indicator->id,
            'habit_date' => '2026-09-20', 'initiative' => 'mandiri',
        ]);

        $token = auth('api')->login($teacherA);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/habits/entries/{$entry->id}");

        $response->assertStatus(404);
    }

    /** @test */
    public function invalid_date_format_ditolak_422(): void
    {
        [$school, $ay, $rombel] = $this->makeSchoolWithRombel();
        $teacherUser = $this->makeTeacher($school, $ay, $rombel, 'guru8');

        $token = auth('api')->login($teacherUser);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/habits/entries?date=20-09-2026');

        $response->assertStatus(422);
    }

    /** @test */
    public function query_student_id_diabaikan_bukan_filter(): void
    {
        [$school, $ay, $rombel] = $this->makeSchoolWithRombel();
        $teacherUser = $this->makeTeacher($school, $ay, $rombel, 'guru9');
        $student1 = $this->makeStudent($school, $ay, $rombel, '0091');
        $student2 = $this->makeStudent($school, $ay, $rombel, '0092');
        [$habit, $indicator] = $this->makeHabitIndicator();

        StudentHabit::create([
            'student_id' => $student1->id, 'habit_id' => $habit->id, 'indicator_id' => $indicator->id,
            'habit_date' => '2026-09-20', 'initiative' => 'mandiri',
        ]);
        StudentHabit::create([
            'student_id' => $student2->id, 'habit_id' => $habit->id, 'indicator_id' => $indicator->id,
            'habit_date' => '2026-09-20', 'initiative' => 'mandiri',
        ]);

        $token = auth('api')->login($teacherUser);

        // Kirim student_id di query — HARUS diabaikan (scope tetap dari active
        // assignment Teacher, bukan dari parameter ini). Hasil tetap 2 (semua
        // Student di rombel Teacher), bukan ter-filter jadi 1.
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/habits/entries?student_id={$student1->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    /** @test */
    public function tidak_ada_route_patch_atau_delete_untuk_habit_entry(): void
    {
        [$school, $ay, $rombel] = $this->makeSchoolWithRombel();
        $teacherUser = $this->makeTeacher($school, $ay, $rombel, 'guru10');
        $student = $this->makeStudent($school, $ay, $rombel, '0101');
        [$habit, $indicator] = $this->makeHabitIndicator();

        $entry = StudentHabit::create([
            'student_id' => $student->id, 'habit_id' => $habit->id, 'indicator_id' => $indicator->id,
            'habit_date' => '2026-09-20', 'initiative' => 'mandiri',
        ]);

        $token = auth('api')->login($teacherUser);

        // GET /habits/entries/{id} terdaftar (untuk show detail), jadi PATCH/DELETE
        // ke path yang sama membalas 405 Method Not Allowed (path ada, method tidak
        // terdaftar) — bukan 404. 405 di sini justru MEMBUKTIKAN tidak ada endpoint
        // PATCH/DELETE untuk habit entry, sesuai NOTE-08 (immutable).
        $patch = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/habits/entries/{$entry->id}", ['note' => 'x']);
        $patch->assertStatus(405);

        $delete = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/habits/entries/{$entry->id}");
        $delete->assertStatus(405);
    }
}
