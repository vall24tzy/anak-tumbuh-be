<?php

namespace Tests\Feature\Student;

use App\Models\ImportBatch;
use App\Models\Student;
use App\Models\StudentClassAssignment;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\CreatesSchoolFixtures;
use Tests\TestCase;

// F-53 (keputusan FINAL pemilik): NISN Student yang sama dengan users.username milik SA/Headmaster/Teacher
// ditolak 422 `nisn_conflicts_with_staff_username` (bukan 500), divalidasi di service SEBELUM insert.
// NISN yang sama dengan NISN Student lain (re-enrollment) tetap sah dan tetap di-reuse: tidak ada constraint
// unique baru, username Student tetap = NISN. Berlaku untuk POST /students dan Student Import (per baris).
//
// STATUS EKSEKUSI: ditulis di sandbox tanpa PHP/PostgreSQL; hasil run lihat laporan patch.

class StudentNisnStaffCollisionTest extends TestCase
{
    use RefreshDatabase, CreatesSchoolFixtures;

    protected $school;
    protected $rombel;
    protected User $teacher;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = $this->fxSchool();
        $this->rombel = $this->fxRombel($this->school, $this->fxAcademicYear($this->school), 'VII-A');
        $this->teacher = $this->fxTeacher($this->school, '2001');
        $this->fxTeacherAssignment($this->teacher->teacher, $this->rombel);
        $this->token = $this->fxToken($this->teacher);
    }

    protected function createStudent(string $nisn)
    {
        return $this->apiPost('/api/v1/students', $this->token, [
            'rombel_id' => $this->rombel->id, 'name' => 'Siswa Uji', 'nisn' => $nisn, 'gender' => 'laki-laki',
        ]);
    }

    protected function assertRejectedAsStaffCollision($response, string $nisn): void
    {
        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'nisn_conflicts_with_staff_username')
            ->assertJsonPath('error.details.0.field', 'nisn');

        $this->assertSame(0, Student::where('nisn', $nisn)->count(), 'Student tidak boleh dibuat');
        $this->assertSame(0, User::where('username', $nisn)->where('role', 'student')->count(), 'akun Student tidak boleh dibuat');
    }

    // ---------- 1-2: collision dengan akun staf ----------

    /** @test */
    public function nisn_sama_dengan_username_super_admin_422_dan_student_tidak_dibuat(): void
    {
        $this->fxSuperAdmin('9900001234');

        $this->assertRejectedAsStaffCollision($this->createStudent('9900001234'), '9900001234');
    }

    /** @test */
    public function nisn_sama_dengan_nip_headmaster_422_dan_student_tidak_dibuat(): void
    {
        $this->fxHeadmaster($this->school, '1960010100');

        $this->assertRejectedAsStaffCollision($this->createStudent('1960010100'), '1960010100');
    }

    /** @test */
    public function nisn_sama_dengan_nip_teacher_422_baik_teacher_sekolah_sama_maupun_sekolah_lain(): void
    {
        $this->fxTeacher($this->school, '1970020200');
        $this->fxTeacher($this->fxSchool('SD Lain'), '1970030300');

        $this->assertRejectedAsStaffCollision($this->createStudent('1970020200'), '1970020200');
        $this->assertRejectedAsStaffCollision($this->createStudent('1970030300'), '1970030300');
    }

    // ---------- 3: reuse sah TIDAK boleh ikut ditolak (test negatif terpenting) ----------

    /** @test */
    public function nisn_sama_dengan_nisn_student_lain_tetap_sukses_reuse_bukan_collision(): void
    {
        $existing = $this->fxStudent($this->school, '0051110001'); // Student lama, belum punya assignment aktif
        $existingStudentId = $existing->student->id;
        $usersBefore = User::count();
        $studentsBefore = Student::count();

        $response = $this->createStudent('0051110001')->assertStatus(201);

        $this->assertSame($existingStudentId, $response->json('data.id'), 'harus Student yang sama (reuse), bukan identity baru');
        $this->assertSame($usersBefore, User::count(), 'tidak ada akun baru');
        $this->assertSame($studentsBefore, Student::count(), 'tidak ada Student baru');
        $this->assertSame(1, StudentClassAssignment::where('student_id', $existingStudentId)->where('rombel_id', $this->rombel->id)->count());
        $this->assertSame('student', User::where('username', '0051110001')->value('role'));
    }

    /** @test */
    public function re_enrollment_student_dengan_riwayat_assignment_ended_tetap_sukses(): void
    {
        $existing = $this->fxStudent($this->school, '0051110002');
        $old = $this->fxRombel($this->school, $this->rombel->academicYear ?? \App\Models\AcademicYear::first(), 'VI-A');
        $this->fxStudentAssignment($existing->student, $old)->update(['status' => 'ended']);

        $this->createStudent('0051110002')->assertStatus(201);

        $this->assertSame(1, Student::where('nisn', '0051110002')->count());
        $this->assertSame(2, StudentClassAssignment::where('student_id', $existing->student->id)->count());
    }

    /** @test */
    public function nisn_baru_yang_tidak_bentrok_tetap_membuat_student_baru_seperti_biasa(): void
    {
        $this->createStudent('0051110003')->assertStatus(201);

        $this->assertSame(1, Student::where('nisn', '0051110003')->count());
        $this->assertSame('student', User::where('username', '0051110003')->value('role'));
    }

    /** @test */
    public function nisn_student_sekolah_lain_tetap_403_cross_school_bukan_422(): void
    {
        $this->fxStudent($this->fxSchool('SD Lain'), '0051110004');

        $this->createStudent('0051110004')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'cross_school_nisn');
    }

    // ---------- 4: Student Import, partial processing ----------

    protected function makeXlsx(array $rows): UploadedFile
    {
        $export = new class($rows) implements FromArray, WithHeadings {
            public function __construct(private array $rows) {}
            public function array(): array { return $this->rows; }
            public function headings(): array { return ['Nama Lengkap', 'NISN', 'Gender']; }
        };

        $path = 'test-imports/' . uniqid('nisn_', true) . '.xlsx';
        Excel::store($export, $path, 'local');

        return new UploadedFile(storage_path('app/' . $path), 'siswa.xlsx', null, null, true);
    }

    /** @test */
    public function import_baris_bentrok_staf_gagal_per_baris_field_nisn_baris_lain_tetap_diproses(): void
    {
        $this->fxTeacher($this->school, '1980040400'); // NIP guru lain sekolah ini
        $this->fxStudent($this->school, '0051110005'); // reuse sah di tengah file

        $response = $this->withHeaders(['Accept' => 'application/json', 'Authorization' => 'Bearer ' . $this->token])
            ->post('/api/v1/student-imports/commit', [
                'file'      => $this->makeXlsx([
                    ['Siswa Satu', '0051110006', 'laki-laki'],   // baris 2: valid, baru
                    ['Siswa Bentrok', '1980040400', 'perempuan'], // baris 3: bentrok username Teacher
                    ['Siswa Reuse', '0051110005', 'laki-laki'],   // baris 4: NISN Student lama, reuse sah
                    ['Siswa Dua', '0051110007', 'perempuan'],     // baris 5: valid, baru
                ]),
                'rombel_id' => $this->rombel->id,
            ])->assertStatus(200);

        $this->assertSame(4, $response->json('data.total_rows'));
        $this->assertSame(3, $response->json('data.successful_rows'));
        $this->assertSame(1, $response->json('data.rejected_rows'));

        $error = $response->json('data.errors.0');
        $this->assertSame(3, $error['row']);
        $this->assertSame('nisn', $error['field']);
        $this->assertSame('nisn_conflicts_with_staff_username', $error['code']);

        $this->assertSame(0, Student::where('nisn', '1980040400')->count());
        $this->assertSame(1, Student::where('nisn', '0051110006')->count());
        $this->assertSame(1, Student::where('nisn', '0051110007')->count());
        $this->assertSame('done', ImportBatch::findOrFail($response->json('data.batch_id'))->status);
    }

    // ---------- 5: race window (jaring pengaman) ----------

    /**
     * Simulasi: akun staf ber-username sama muncul DI ANTARA pre-check dan INSERT. Pemicunya DB::listen pada query
     * pre-check (users + role + nisn), jadi baris staf dibuat setelah pre-check lolos tetapi sebelum INSERT Student.
     * INSERT lalu menabrak constraint users.username yang SUDAH ada dan diterjemahkan jadi 422 (bukan 500).
     * Window-nya sendiri tidak bisa ditutup tanpa constraint unique global pada NISN, yang dilarang keputusan F-53.
     *
     * @test
     */
    public function race_akun_staf_muncul_setelah_pre_check_tetap_422_bukan_500(): void
    {
        $nisn = '0051110008';
        $fired = false;

        DB::listen(function (QueryExecuted $q) use ($nisn, &$fired) {
            if ($fired || !str_contains($q->sql, '"users"') || !str_contains($q->sql, '"role"') || !in_array($nisn, $q->bindings, true)) {
                return;
            }

            $fired = true;
            User::create(['role' => 'teacher', 'username' => $nisn, 'password' => 'secret']);
        });

        $this->assertRejectedAsStaffCollision(
            $this->createStudent($nisn),
            $nisn
        );
        $this->assertTrue($fired, 'simulasi race harus benar-benar terpicu');
        $this->assertSame(1, User::where('username', $nisn)->count(), 'hanya akun staf yang tersisa');
    }
}
