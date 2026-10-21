<?php

namespace Tests\Feature\Student;

use App\Models\AcademicYear;
use App\Models\Headmaster;
use App\Models\QrToken;
use App\Models\Rombel;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentClassAssignment;
use App\Models\Teacher;
use App\Models\TeacherClassAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

// INTEG-QR-001 — Matrix #69, Dokumen Keputusan v4.0 (amandemen 20 Okt 2026, g).
// Field qr_regenerated_at pada GET /students (LIST), HANYA untuk Teacher.
// Tidak ada pada GET /students/{id}, tidak ada untuk role lain. Lihat
// docs/addendum/API-Contract-v1.2-Addendum-QR-Regenerated-At.md.
//
// Setup memakai Model::create() berantai (bukan factory), sama seperti
// StudentListPaginationTest.

class StudentListQrRegeneratedAtTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    /** @return array{0: School, 1: AcademicYear, 2: Rombel, 3: User} */
    protected function setUpSchoolWithTeacher(string $schoolName, string $nip): array
    {
        $school = School::create(['name' => $schoolName]);

        $year = AcademicYear::create([
            'school_id'  => $school->id,
            'name'       => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date'   => '2027-06-30',
            'is_active'  => true,
        ]);

        $rombel = Rombel::create([
            'school_id'        => $school->id,
            'academic_year_id' => $year->id,
            'name'             => 'VII-A',
            'grade_level'      => 7,
        ]);

        return [$school, $year, $rombel, $this->createTeacherFor($school, $year, $rombel, $nip)];
    }

    protected function createTeacherFor(School $school, AcademicYear $year, Rombel $rombel, string $nip): User
    {
        $user = User::create(['role' => 'teacher', 'username' => $nip, 'password' => 'secret']);

        $teacher = Teacher::create([
            'user_id'   => $user->id,
            'school_id' => $school->id,
            'fullname'  => 'Bu Guru ' . $nip,
            'nip'       => $nip,
            'gender'    => 'perempuan',
            'status'    => 'active',
        ]);

        TeacherClassAssignment::create([
            'teacher_id'       => $teacher->id,
            'school_id'        => $school->id,
            'academic_year_id' => $year->id,
            'rombel_id'        => $rombel->id,
            'is_active'        => true,
        ]);

        return $user;
    }

    // Meniru POST /students: baris qr_tokens dibuat TANPA regenerated_at.
    protected function createStudentInRombel(School $school, Rombel $rombel, AcademicYear $year): Student
    {
        $nisn = '71' . str_pad((string) (++$this->seq), 8, '0', STR_PAD_LEFT);

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
            'academic_year_id' => $year->id,
            'status'           => 'active',
        ]);

        QrToken::create([
            'student_id' => $student->id,
            'token_hash' => Hash::make((string) Str::uuid()),
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

    protected function asUser(User $user): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login($user));
    }

    protected function rowFor(array $rows, int $studentId): ?array
    {
        foreach ($rows as $row) {
            if ($row['id'] === $studentId) {
                return $row;
            }
        }

        return null;
    }

    /** @test */
    public function teacher_list_memuat_qr_regenerated_at_null_bila_belum_pernah_regenerate(): void
    {
        [$school, $year, $rombel, $teacherUser] = $this->setUpSchoolWithTeacher('SD A', '1001');
        $student = $this->createStudentInRombel($school, $rombel, $year);

        $response = $this->asUser($teacherUser)->getJson('/api/v1/students');

        $response->assertStatus(200);
        $row = $this->rowFor($response->json('data'), $student->id);
        $this->assertNotNull($row);
        $this->assertArrayHasKey('qr_regenerated_at', $row);
        $this->assertNull($row['qr_regenerated_at']);
    }

    /** @test */
    public function teacher_list_terisi_iso8601_utc_setelah_regenerate_dan_hanya_siswa_itu(): void
    {
        [$school, $year, $rombel, $teacherUser] = $this->setUpSchoolWithTeacher('SD A', '1001');
        $target = $this->createStudentInRombel($school, $rombel, $year);
        $other  = $this->createStudentInRombel($school, $rombel, $year);

        $this->asUser($teacherUser)
            ->postJson("/api/v1/qr-tokens/{$target->id}/regenerate")
            ->assertStatus(200);

        $rows = $this->asUser($teacherUser)->getJson('/api/v1/students')->json('data');

        $targetRow = $this->rowFor($rows, $target->id);
        $this->assertNotNull($targetRow['qr_regenerated_at']);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
            $targetRow['qr_regenerated_at']
        );

        $expected = QrToken::where('student_id', $target->id)->first()
            ->regenerated_at->clone()->utc()->toIso8601ZuluString();
        $this->assertSame($expected, $targetRow['qr_regenerated_at']);

        $this->assertNull($this->rowFor($rows, $other->id)['qr_regenerated_at']);
    }

    /** @test */
    public function siswa_tanpa_baris_qr_token_tetap_muncul_dengan_qr_regenerated_at_null(): void
    {
        [$school, $year, $rombel, $teacherUser] = $this->setUpSchoolWithTeacher('SD A', '1001');
        $student = $this->createStudentInRombel($school, $rombel, $year);
        QrToken::where('student_id', $student->id)->delete();

        $response = $this->asUser($teacherUser)->getJson('/api/v1/students');

        $response->assertStatus(200);
        $row = $this->rowFor($response->json('data'), $student->id);
        $this->assertNotNull($row);
        $this->assertArrayHasKey('qr_regenerated_at', $row);
        $this->assertNull($row['qr_regenerated_at']);
    }

    /** @test */
    public function teacher_show_siswa_tidak_memuat_qr_regenerated_at(): void
    {
        [$school, $year, $rombel, $teacherUser] = $this->setUpSchoolWithTeacher('SD A', '1001');
        $student = $this->createStudentInRombel($school, $rombel, $year);

        $this->asUser($teacherUser)->postJson("/api/v1/qr-tokens/{$student->id}/regenerate")->assertStatus(200);

        $response = $this->asUser($teacherUser)->getJson("/api/v1/students/{$student->id}");

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('qr_regenerated_at', $response->json('data'));
    }

    /** @test */
    public function field_lama_pada_list_teacher_tidak_berubah(): void
    {
        [$school, $year, $rombel, $teacherUser] = $this->setUpSchoolWithTeacher('SD A', '1001');
        $this->createStudentInRombel($school, $rombel, $year);

        $this->asUser($teacherUser)->getJson('/api/v1/students')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['*' => ['id', 'fullname', 'nisn', 'gender', 'photo', 'poin', 'status', 'school' => ['id', 'name'], 'username']],
                'meta' => ['page', 'per_page', 'total', 'total_pages'],
            ]);
    }

    /** @test */
    public function headmaster_super_admin_dan_student_tidak_melihat_field(): void
    {
        [$school, $year, $rombel, $teacherUser] = $this->setUpSchoolWithTeacher('SD A', '1001');
        $student = $this->createStudentInRombel($school, $rombel, $year);
        $this->asUser($teacherUser)->postJson("/api/v1/qr-tokens/{$student->id}/regenerate")->assertStatus(200);

        $headmaster = $this->createHeadmaster($school, '2001');
        $superAdmin = User::create(['role' => 'super_admin', 'username' => 'sa-integ-qr', 'password' => 'secret']);

        foreach ([$headmaster, $superAdmin, $student->user] as $user) {
            $response = $this->asUser($user)->getJson('/api/v1/students');

            $response->assertStatus(200);
            $rows = $response->json('data');
            $this->assertNotEmpty($rows, 'role ' . $user->role . ' harus melihat minimal satu baris');
            foreach ($rows as $row) {
                $this->assertArrayNotHasKey('qr_regenerated_at', $row, 'bocor ke role ' . $user->role);
            }
        }
    }

    /** @test */
    public function teacher_lintas_rombel_dan_lintas_sekolah_tidak_melihat_siswa_lain(): void
    {
        [$school, $year, $rombelA, $teacherA] = $this->setUpSchoolWithTeacher('SD A', '1001');
        $studentA = $this->createStudentInRombel($school, $rombelA, $year);

        // Lintas rombel (sekolah yang sama).
        $rombelB = Rombel::create([
            'school_id'        => $school->id,
            'academic_year_id' => $year->id,
            'name'             => 'VII-B',
            'grade_level'      => 7,
        ]);
        $teacherB = $this->createTeacherFor($school, $year, $rombelB, '1002');
        $studentB = $this->createStudentInRombel($school, $rombelB, $year);

        // Lintas sekolah.
        [$schoolC, $yearC, $rombelC, $teacherC] = $this->setUpSchoolWithTeacher('SD C', '1003');
        $studentC = $this->createStudentInRombel($schoolC, $rombelC, $yearC);

        $idsOf = fn (User $u) => array_column($this->asUser($u)->getJson('/api/v1/students')->json('data'), 'id');

        $this->assertSame([$studentA->id], $idsOf($teacherA));
        $this->assertSame([$studentB->id], $idsOf($teacherB));
        $this->assertSame([$studentC->id], $idsOf($teacherC));

        // Teacher tidak bisa regenerate (dan dengan begitu tidak mengubah field) siswa di luar rombelnya.
        $this->asUser($teacherA)->postJson("/api/v1/qr-tokens/{$studentB->id}/regenerate")->assertStatus(403);
        $this->assertNull(QrToken::where('student_id', $studentB->id)->first()->regenerated_at);
    }

    /** @test */
    public function list_teacher_tanpa_n_plus_1_untuk_qr_token(): void
    {
        [$school, $year, $rombel, $teacherUser] = $this->setUpSchoolWithTeacher('SD A', '1001');
        $this->createStudentInRombel($school, $rombel, $year);
        $this->createStudentInRombel($school, $rombel, $year);

        $countQueries = function () use ($teacherUser): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->asUser($teacherUser)->getJson('/api/v1/students')->assertStatus(200);
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $withTwo = $countQueries();

        for ($i = 0; $i < 6; $i++) {
            $this->createStudentInRombel($school, $rombel, $year);
        }

        $this->assertSame($withTwo, $countQueries(), 'jumlah query harus sama untuk 2 dan 8 siswa');
    }

    /** @test */
    public function list_tanpa_token_ditolak_401(): void
    {
        $this->getJson('/api/v1/students')->assertStatus(401);
    }
}
