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
use App\Exports\StudentImportTemplateExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Tests\TestCase;

// IMPORT-001 — API Contract v1.2 §18/§28, Dokumen v4.0 §6.2.
// Setup pakai Model::create() berantai, konsisten dengan task lain.
//
// STATUS EKSEKUSI: dijalankan di PHP + PostgreSQL; hasil terkini lihat docs/testing/BASELINE-STAGE-0C.md

class StudentImportTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;
    protected AcademicYear $academicYear;
    protected Rombel $rombel;
    protected User $teacherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'SD Contoh']);

        $this->academicYear = AcademicYear::create([
            'school_id'  => $this->school->id,
            'name'       => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date'   => '2027-06-30',
            'is_active'  => true,
        ]);

        $this->rombel = Rombel::create([
            'school_id'        => $this->school->id,
            'academic_year_id' => $this->academicYear->id,
            'name'             => 'VII-A',
            'grade_level'      => 7,
        ]);

        $this->teacherUser = User::create([
            'role'     => 'teacher',
            'username' => '199001012020121001',
            'password' => 'secret',
        ]);

        $teacher = Teacher::create([
            'user_id'   => $this->teacherUser->id,
            'school_id' => $this->school->id,
            'fullname'  => 'Bu Guru',
            'nip'       => '199001012020121001',
            'gender'    => 'perempuan',
            'status'    => 'active',
        ]);

        TeacherClassAssignment::create([
            'teacher_id'       => $teacher->id,
            'school_id'        => $this->school->id,
            'academic_year_id' => $this->academicYear->id,
            'rombel_id'        => $this->rombel->id,
            'is_active'        => true,
        ]);
    }

    protected function authHeader(User $user): array
    {
        $token = auth('api')->login($user);
        return ['Authorization' => "Bearer {$token}"];
    }

    /**
     * Bikin file .xlsx asli di storage sementara dari array baris, dibungkus
     * jadi UploadedFile supaya bisa dipakai postJson()->attach() ala multipart.
     */
    protected function makeXlsx(array $rows, array $headings = ['Nama Lengkap', 'NISN', 'Gender']): UploadedFile
    {
        $export = new class($rows, $headings) implements FromArray, WithHeadings {
            public function __construct(private array $rows, private array $headings) {}
            public function array(): array { return $this->rows; }
            public function headings(): array { return $this->headings; }
        };

        $path = 'test-imports/' . uniqid('siswa_', true) . '.xlsx';
        Excel::store($export, $path, 'local');

        $fullPath = storage_path('app/' . $path);

        return new UploadedFile($fullPath, 'siswa.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    /** @test */
    public function template_tidak_punya_kolom_classroom_grade_level_email(): void
    {
        $export = new StudentImportTemplateExport();

        $this->assertSame(['Nama Lengkap', 'NISN', 'Gender'], $export->headings());
    }

    /** @test */
    public function preview_valid_rows_mengembalikan_valid_count_sesuai(): void
    {
        $file = $this->makeXlsx([
            ['Siswa Satu', '0051111111', 'laki-laki'],
            ['Siswa Dua', '0052222222', 'perempuan'],
        ]);

        $response = $this->withHeaders($this->authHeader($this->teacherUser))
            ->post('/api/v1/student-imports/preview', ['file' => $file]);

        $response->assertStatus(200);
        $this->assertSame(2, $response->json('data.valid_count'));
        $this->assertSame(0, $response->json('data.invalid_count'));
    }

    /** @test */
    public function commit_nisn_baru_berhasil_create_student_dan_assignment(): void
    {
        $file = $this->makeXlsx([
            ['Siswa Baru', '0059999999', 'laki-laki'],
        ]);

        $response = $this->withHeaders($this->authHeader($this->teacherUser))
            ->post('/api/v1/student-imports/commit', [
                'file'      => $file,
                'rombel_id' => $this->rombel->id,
            ]);

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('data.successful_rows'));
        $this->assertSame(0, $response->json('data.rejected_rows'));

        $this->assertDatabaseHas('students', ['nisn' => '0059999999', 'school_id' => $this->school->id]);
        $this->assertDatabaseHas('student_class_assignments', [
            'rombel_id' => $this->rombel->id,
            'status'    => 'active',
        ]);
    }

    /** @test */
    public function commit_nisn_existing_sekolah_sama_direuse_bukan_direjek(): void
    {
        $existingUser = User::create(['role' => 'student', 'username' => '0058888888', 'password' => '0058888888']);
        Student::create([
            'user_id'   => $existingUser->id,
            'school_id' => $this->school->id,
            'fullname'  => 'Siswa Lama',
            'nisn'      => '0058888888',
            'gender'    => 'laki-laki',
            'poin'      => 0,
            'status'    => 'active',
        ]);
        // TIDAK ada assignment aktif untuk Student ini di academicYear ini
        // (mis. baru pindah dari sekolah lain / belum pernah di-assign).

        $file = $this->makeXlsx([
            ['Siswa Lama', '0058888888', 'laki-laki'],
        ]);

        $response = $this->withHeaders($this->authHeader($this->teacherUser))
            ->post('/api/v1/student-imports/commit', [
                'file'      => $file,
                'rombel_id' => $this->rombel->id,
            ]);

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('data.successful_rows'));

        // Tidak ada User/Student baru dibuat untuk NISN yang sama.
        $this->assertSame(1, User::where('username', '0058888888')->count());
        $this->assertSame(1, Student::where('nisn', '0058888888')->count());
    }

    /** @test */
    public function commit_duplicate_assignment_dilaporkan_dengan_code_yang_benar(): void
    {
        // Siswa sudah punya assignment aktif di rombel & tahun yang SAMA.
        $studentUser = User::create(['role' => 'student', 'username' => '0057777777', 'password' => '0057777777']);
        $student = Student::create([
            'user_id' => $studentUser->id, 'school_id' => $this->school->id,
            'fullname' => 'Siswa Duplikat', 'nisn' => '0057777777',
            'gender' => 'laki-laki', 'poin' => 0, 'status' => 'active',
        ]);
        StudentClassAssignment::create([
            'student_id' => $student->id, 'school_id' => $this->school->id,
            'rombel_id' => $this->rombel->id, 'academic_year_id' => $this->academicYear->id,
            'status' => 'active',
        ]);

        $file = $this->makeXlsx([
            ['Siswa Duplikat', '0057777777', 'laki-laki'],
        ]);

        $response = $this->withHeaders($this->authHeader($this->teacherUser))
            ->post('/api/v1/student-imports/commit', [
                'file'      => $file,
                'rombel_id' => $this->rombel->id,
            ]);

        $response->assertStatus(200);
        $this->assertSame(0, $response->json('data.successful_rows'));
        $this->assertSame(1, $response->json('data.rejected_rows'));
        $this->assertSame('duplicate_assignment', $response->json('data.errors.0.code'));
        // Baris 1 file Excel = header, jadi baris data pertama = baris 2
        // (ImportService::validateRows(): $rowNumber = $index + 2).
        $this->assertSame(2, $response->json('data.errors.0.row'));
    }

    /** @test */
    public function commit_row_validasi_gagal_dilaporkan_code_validation_failed(): void
    {
        $file = $this->makeXlsx([
            ['', '0056666666', 'laki-laki'], // Nama Lengkap kosong
        ]);

        $response = $this->withHeaders($this->authHeader($this->teacherUser))
            ->post('/api/v1/student-imports/commit', [
                'file'      => $file,
                'rombel_id' => $this->rombel->id,
            ]);

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('data.rejected_rows'));
        $this->assertSame('validation_failed', $response->json('data.errors.0.code'));
        $this->assertSame('name', $response->json('data.errors.0.field'));
    }

    /** @test */
    public function commit_rombel_id_bukan_own_active_rombel_ditolak_403_seluruh_request(): void
    {
        $rombelLain = Rombel::create([
            'school_id' => $this->school->id, 'academic_year_id' => $this->academicYear->id,
            'name' => 'VII-B', 'grade_level' => 7,
        ]);

        $file = $this->makeXlsx([
            ['Siswa Satu', '0051111111', 'laki-laki'],
        ]);

        $response = $this->withHeaders($this->authHeader($this->teacherUser))
            ->post('/api/v1/student-imports/commit', [
                'file'      => $file,
                'rombel_id' => $rombelLain->id,
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('students', ['nisn' => '0051111111']);
    }

    /** @test */
    public function super_admin_dan_headmaster_tidak_bisa_akses_endpoint_import_403(): void
    {
        $saUser = User::create(['role' => 'super_admin', 'username' => 'admin1', 'password' => 'secret']);

        $file = $this->makeXlsx([['Siswa Satu', '0051111111', 'laki-laki']]);

        $response = $this->withHeaders($this->authHeader($saUser))
            ->post('/api/v1/student-imports/commit', [
                'file'      => $file,
                'rombel_id' => $this->rombel->id,
            ]);

        $response->assertStatus(403);
    }
}
