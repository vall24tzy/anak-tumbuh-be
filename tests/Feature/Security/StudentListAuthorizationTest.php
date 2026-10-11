<?php

namespace Tests\Feature\Security;

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

// SECURITY-001 (bagian yang tertahan; C-02) - uji otorisasi lintas sekolah/rombel untuk
// GET /students dan GET /student-class-assignments yang kini dipaginasi.
//
// Yang dikunci untuk KEDUA endpoint:
//   1. Teacher hanya melihat Student rombel aktifnya; tidak ada Student rombel lain di sekolah yang sama maupun
//      sekolah lain, dan meta.total hanya menghitung data dalam scope.
//   2. Headmaster hanya melihat sekolahnya.
//   3. Student hanya melihat dirinya (data dan total).
//   4. Halaman di luar jangkauan: kosong, total tetap hanya dalam scope.
//   5. Teacher/Headmaster inactive ditolak 403 (pola F-14), tanpa data dan tanpa meta.
//
// Test ini TIDAK memperbaiki apa pun. Bila ada test merah, itu temuan perilaku (BUG) yang dilaporkan, bukan
// alasan mengubah test supaya lulus.
//
// Setup memakai Model::create() berantai (pola StudentListPaginationTest), bukan factory.
//
// Dunia data (jumlah sengaja berbeda-beda supaya kebocoran total terlihat):
//   Sekolah A: rombel A1 (Teacher A1) = 3 Student, rombel A2 (Teacher A2) = 2 Student  -> sekolah A = 5
//   Sekolah B: rombel B1 (Teacher B1) = 4 Student                                      -> sekolah B = 4
//
// STATUS EKSEKUSI: ditulis di sandbox tanpa PHP; jalankan
// `php artisan test --filter=StudentListAuthorizationTest`.

class StudentListAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private School $schoolA;
    private School $schoolB;

    /** @var Student[] */
    private array $a1 = [];
    /** @var Student[] */
    private array $a2 = [];
    /** @var Student[] */
    private array $b1 = [];

    private User $teacherA1;
    private User $teacherB1;
    private User $headmasterA;
    private User $headmasterB;

    /** @return array<string, array{0: string, 1: string}> [url, kunci id Student pada tiap item data] */
    public static function endpoints(): array
    {
        return [
            'GET /students'                 => ['/api/v1/students', 'id'],
            'GET /student-class-assignments' => ['/api/v1/student-class-assignments', 'student_id'],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolA = School::create(['name' => 'SD A']);
        $this->schoolB = School::create(['name' => 'SD B']);

        $yearA = $this->createYear($this->schoolA);
        $yearB = $this->createYear($this->schoolB);

        [$rombelA1, $this->teacherA1] = $this->createRombelWithTeacher($this->schoolA, $yearA, 'VII-A', '1001');
        [$rombelA2] = $this->createRombelWithTeacher($this->schoolA, $yearA, 'VII-B', '1002');
        [$rombelB1, $this->teacherB1] = $this->createRombelWithTeacher($this->schoolB, $yearB, 'VII-A', '1003');

        for ($i = 0; $i < 3; $i++) {
            $this->a1[] = $this->createStudent($this->schoolA, $rombelA1, $yearA);
        }
        for ($i = 0; $i < 2; $i++) {
            $this->a2[] = $this->createStudent($this->schoolA, $rombelA2, $yearA);
        }
        for ($i = 0; $i < 4; $i++) {
            $this->b1[] = $this->createStudent($this->schoolB, $rombelB1, $yearB);
        }

        $this->headmasterA = $this->createHeadmaster($this->schoolA, '5001');
        $this->headmasterB = $this->createHeadmaster($this->schoolB, '5002');
    }

    // ---------- helper setup ----------

    protected function createYear(School $school): AcademicYear
    {
        return AcademicYear::create([
            'school_id'  => $school->id,
            'name'       => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date'   => '2027-06-30',
            'is_active'  => true,
        ]);
    }

    /** @return array{0: Rombel, 1: User} */
    protected function createRombelWithTeacher(School $school, AcademicYear $year, string $rombelName, string $nip): array
    {
        $rombel = Rombel::create([
            'school_id'        => $school->id,
            'academic_year_id' => $year->id,
            'name'             => $rombelName,
            'grade_level'      => 7,
        ]);

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

        return [$rombel, $user];
    }

    protected function createStudent(School $school, Rombel $rombel, AcademicYear $year): Student
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
            'academic_year_id' => $year->id,
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

    // ---------- helper request/assert ----------

    protected function getAs(User $user, string $url)
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login($user))->getJson($url);
    }

    protected function ids(Student ...$students): array
    {
        return array_map(fn (Student $s) => $s->id, $students);
    }

    /** Respons tidak boleh memuat NISN Student mana pun di luar scope. */
    protected function assertNoLeak($response, array $outOfScope): void
    {
        $body = $response->getContent();

        foreach ($outOfScope as $student) {
            $this->assertStringNotContainsString($student->nisn, $body, "NISN {$student->nisn} (di luar scope) bocor di respons");
        }
    }

    // ---------- 1. Teacher ----------

    /**
     * @test
     * @dataProvider endpoints
     */
    public function teacher_hanya_melihat_student_rombel_aktifnya_dan_total_hanya_dalam_scope(string $url, string $key): void
    {
        $response = $this->getAs($this->teacherA1, $url);

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 3)          // bukan 5 (sekolah A) dan bukan 9 (semua)
            ->assertJsonPath('meta.total_pages', 1);

        $this->assertEqualsCanonicalizing($this->ids(...$this->a1), array_column($response->json('data'), $key));
        $this->assertNoLeak($response, [...$this->a2, ...$this->b1]);
    }

    /**
     * @test
     * @dataProvider endpoints
     */
    public function teacher_scope_tetap_sama_di_seluruh_halaman_dan_total_tidak_membocorkan_data_lain(string $url, string $key): void
    {
        $p1 = $this->getAs($this->teacherA1, "{$url}?per_page=2&page=1");
        $p2 = $this->getAs($this->teacherA1, "{$url}?per_page=2&page=2");

        foreach ([$p1, $p2] as $page) {
            $page->assertStatus(200)
                ->assertJsonPath('meta.total', 3)
                ->assertJsonPath('meta.total_pages', 2);
            $this->assertNoLeak($page, [...$this->a2, ...$this->b1]);
        }

        $p1->assertJsonCount(2, 'data');
        $p2->assertJsonCount(1, 'data');

        $union = [...array_column($p1->json('data'), $key), ...array_column($p2->json('data'), $key)];
        $this->assertEqualsCanonicalizing($this->ids(...$this->a1), $union);
    }

    /**
     * @test
     * @dataProvider endpoints
     */
    public function teacher_sekolah_lain_hanya_melihat_rombelnya_sendiri(string $url, string $key): void
    {
        $response = $this->getAs($this->teacherB1, $url);

        $response->assertStatus(200)->assertJsonPath('meta.total', 4);

        $this->assertEqualsCanonicalizing($this->ids(...$this->b1), array_column($response->json('data'), $key));
        $this->assertNoLeak($response, [...$this->a1, ...$this->a2]);
    }

    // ---------- 2. Headmaster ----------

    /**
     * @test
     * @dataProvider endpoints
     */
    public function headmaster_hanya_melihat_sekolahnya_dan_tidak_melihat_sekolah_lain(string $url, string $key): void
    {
        $a = $this->getAs($this->headmasterA, $url);
        $b = $this->getAs($this->headmasterB, $url);

        $a->assertStatus(200)->assertJsonPath('meta.total', 5);    // A1 + A2, bukan 9
        $b->assertStatus(200)->assertJsonPath('meta.total', 4);

        $this->assertEqualsCanonicalizing($this->ids(...$this->a1, ...$this->a2), array_column($a->json('data'), $key));
        $this->assertEqualsCanonicalizing($this->ids(...$this->b1), array_column($b->json('data'), $key));

        $this->assertNoLeak($a, $this->b1);
        $this->assertNoLeak($b, [...$this->a1, ...$this->a2]);
    }

    // ---------- 3. Student ----------

    /**
     * @test
     * @dataProvider endpoints
     */
    public function student_hanya_melihat_dirinya_pada_data_dan_total(string $url, string $key): void
    {
        $self = $this->a1[0];

        $response = $this->getAs($self->user, $url);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.total_pages', 1);

        $this->assertSame([$self->id], array_column($response->json('data'), $key));

        // Teman sekelas, rombel lain di sekolah yang sama, dan sekolah lain tidak muncul.
        $others = [...array_slice($this->a1, 1), ...$this->a2, ...$this->b1];
        $this->assertNoLeak($response, $others);
    }

    // ---------- 4. Halaman di luar jangkauan ----------

    /**
     * @test
     * @dataProvider endpoints
     */
    public function halaman_di_luar_jangkauan_kosong_dan_total_tetap_dalam_scope(string $url, string $key): void
    {
        $cases = [
            // [user, query, total dalam scope, total_pages]
            'teacher: page = total_pages + 1' => [$this->teacherA1, 'per_page=2&page=3', 3, 2],
            'teacher: page jauh di atas'      => [$this->teacherA1, 'page=99', 3, 1],
            'headmaster: page = total_pages + 1' => [$this->headmasterA, 'per_page=2&page=4', 5, 3],
            'student: halaman 2'              => [$this->a1[0]->user, 'page=2', 1, 1],
        ];

        $allOutsiders = [...$this->a1, ...$this->a2, ...$this->b1];

        foreach ($cases as $label => [$user, $query, $total, $totalPages]) {
            $response = $this->getAs($user, "{$url}?{$query}");

            $response->assertStatus(200);
            $response->assertJsonCount(0, 'data');
            $this->assertSame($total, $response->json('meta.total'), "{$label}: total bocor/berubah");
            $this->assertSame($totalPages, $response->json('meta.total_pages'), "{$label}: total_pages tidak sesuai scope");
            $this->assertNoLeak($response, $allOutsiders);
        }
    }

    // ---------- 5. Inactive ditolak 403 (pola F-14) ----------

    /**
     * @test
     * @dataProvider endpoints
     */
    public function teacher_inactive_ditolak_403_tanpa_data(string $url, string $key): void
    {
        Teacher::where('user_id', $this->teacherA1->id)->update(['status' => 'inactive']);

        $response = $this->getAs($this->teacherA1, $url);

        $response->assertStatus(403);
        $this->assertArrayNotHasKey('data', $response->json() ?? []);
        $this->assertArrayNotHasKey('meta', $response->json() ?? []);
        $this->assertNoLeak($response, $this->a1);
    }

    /**
     * @test
     * @dataProvider endpoints
     */
    public function headmaster_inactive_ditolak_403_tanpa_data(string $url, string $key): void
    {
        Headmaster::where('user_id', $this->headmasterA->id)->update(['status' => 'inactive']);

        $response = $this->getAs($this->headmasterA, $url);

        $response->assertStatus(403);
        $this->assertArrayNotHasKey('data', $response->json() ?? []);
        $this->assertArrayNotHasKey('meta', $response->json() ?? []);
        $this->assertNoLeak($response, [...$this->a1, ...$this->a2]);
    }
}
