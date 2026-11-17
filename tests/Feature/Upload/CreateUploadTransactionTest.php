<?php

namespace Tests\Feature\Upload;

use App\Models\QrToken;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentClassAssignment;
use App\Models\User;
use App\Services\Upload\UploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSchoolFixtures;
use Tests\TestCase;

// DEPLOY-STORAGE-003 (D-38, S-1, diputuskan Pemilik 16 Nov 2026): foto Student dan logo School pada CREATE diunggah di
// DALAM transaksi DB yang sama. Bila S3 gagal, seluruh transaksi rollback dan retry berhasil (tidak kena 409 NISN atau
// 422 nama sekolah). Hanya jalur create (siswa dan sekolah); jalur update tidak diubah.
//
// Kegagalan S3 disimulasikan dengan mengganti UploadService di container: (a) gagal sebelum file tertulis, (b) file
// tertulis lalu gagal (menguji kompensasi). Test kegagalan put() pada disk S3 sungguhan ada di StorageDiskConfigTest.
//
// Re-enrollment (NISN sudah ada): bila gagal, assignment tidak tercipta dan foto lama utuh; bila berhasil dengan
// ekstensi berbeda, file lama dihapus SESUDAH commit.
//
// STATUS EKSEKUSI: dijalankan di PHP 8.3 + PostgreSQL 16 (sandbox); hasil lihat team-log DEPLOY-STORAGE-003.

class CreateUploadTransactionTest extends TestCase
{
    use RefreshDatabase, CreatesSchoolFixtures;

    private ?User $sa = null;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('private');

        FlakyUploadService::$fail = false;
        $this->app->singleton(UploadService::class, fn () => new FlakyUploadService());
    }

    protected function tearDown(): void
    {
        FlakyUploadService::$fail = false;

        parent::tearDown();
    }

    /**
     * Unggah gagal (mis. S3 menolak/timeout): file belum tertulis. UploadService diganti SEKALI di setUp dengan versi
     * yang bisa dinyalakan/dimatikan, karena controller di-cache oleh Route selama satu test (service tidak bisa
     * ditukar di antara dua request).
     */
    protected function failUploads(): void
    {
        FlakyUploadService::$fail = true;
    }

    /** Unggah SUKSES (file tertulis), lalu pembaruan path di DB gagal: menguji kompensasi file baru. */
    protected function failAfterUpload(string $modelClass): void
    {
        $modelClass::updating(function ($model) {
            if ($model->isDirty(['photo_path', 'logo_path'])) {
                throw new \RuntimeException('simulasi: update path gagal setelah unggah');
            }
        });
    }

    protected function restoreUploads(): void
    {
        FlakyUploadService::$fail = false;
    }

    /** @return array{0: User, 1: \App\Models\Rombel, 2: School} */
    protected function teacherScenario(): array
    {
        $school = $this->fxSchool();
        $rombel = $this->fxRombel($school, $this->fxAcademicYear($school), 'VII-A');
        $teacher = $this->fxTeacher($school, '2001');
        $this->fxTeacherAssignment($teacher->teacher, $rombel);

        return [$teacher, $rombel, $school];
    }

    protected function postStudent(User $teacher, array $fields)
    {
        return $this->withHeaders(['Authorization' => 'Bearer ' . $this->fxToken($teacher), 'Accept' => 'application/json'])
            ->post('/api/v1/students', $fields);
    }

    protected function studentFields($rombel, string $nisn, ?UploadedFile $photo): array
    {
        return array_filter([
            'rombel_id' => $rombel->id, 'name' => 'Siswa Foto', 'nisn' => $nisn, 'gender' => 'laki-laki', 'photo' => $photo,
        ], fn ($v) => $v !== null);
    }

    // ---------- create siswa ----------

    /** @test */
    public function create_siswa_dengan_foto_gagal_unggah_rollback_semua_dan_retry_berhasil(): void
    {
        [$teacher, $rombel] = $this->teacherScenario();
        $nisn = '0051119001';

        $this->failUploads();
        $this->postStudent($teacher, $this->studentFields($rombel, $nisn, UploadedFile::fake()->image('foto.jpg', 60, 60)))
            ->assertStatus(500);

        $this->assertDatabaseMissing('students', ['nisn' => $nisn]);
        $this->assertDatabaseMissing('users', ['username' => $nisn]);
        $this->assertSame(0, QrToken::count());
        $this->assertSame(0, StudentClassAssignment::count());

        $this->restoreUploads();
        $response = $this->postStudent($teacher, $this->studentFields($rombel, $nisn, UploadedFile::fake()->image('foto.jpg', 60, 60)))
            ->assertStatus(201);

        $student = Student::where('nisn', $nisn)->firstOrFail();
        $this->assertSame("students/{$student->id}.jpg", $student->photo_path);
        Storage::disk('private')->assertExists($student->photo_path);
        $this->assertSame(1, StudentClassAssignment::where('student_id', $student->id)->count());
    }

    /** @test */
    public function create_siswa_file_yang_sudah_tertulis_dihapus_bila_gagal_setelah_unggah(): void
    {
        [$teacher, $rombel] = $this->teacherScenario();

        $this->failAfterUpload(Student::class);
        $this->postStudent($teacher, $this->studentFields($rombel, '0051119002', UploadedFile::fake()->image('foto.jpg', 60, 60)))
            ->assertStatus(500);

        $this->assertDatabaseMissing('students', ['nisn' => '0051119002']);
        $this->assertSame([], Storage::disk('private')->allFiles(), 'file baru tidak boleh tertinggal sebagai yatim');
    }

    /** @test */
    public function create_siswa_tanpa_foto_tidak_berubah(): void
    {
        [$teacher, $rombel] = $this->teacherScenario();

        $this->failUploads(); // tidak dipakai: tanpa foto, upload tidak dipanggil
        $this->postStudent($teacher, $this->studentFields($rombel, '0051119003', null))->assertStatus(201);

        $this->assertNull(Student::where('nisn', '0051119003')->value('photo_path'));
    }

    // ---------- re-enrollment ----------

    /** Student sudah ada (tanpa assignment aktif) dengan foto lama students/{id}.png. */
    protected function existingStudentWithOldPhoto(School $school, string $nisn): Student
    {
        $student = $this->fxStudent($school, $nisn)->student;
        $student->update(['photo_path' => "students/{$student->id}.png"]);
        Storage::disk('private')->put($student->photo_path, 'FOTO-LAMA');

        return $student;
    }

    /** @test */
    public function re_enrollment_gagal_unggah_tidak_membuat_assignment_dan_foto_lama_utuh(): void
    {
        [$teacher, $rombel, $school] = $this->teacherScenario();
        $student = $this->existingStudentWithOldPhoto($school, '0051119004');
        $oldPath = $student->photo_path;

        $this->failUploads();
        $this->postStudent($teacher, $this->studentFields($rombel, '0051119004', UploadedFile::fake()->image('foto.jpg', 60, 60)))
            ->assertStatus(500);

        $this->assertSame(0, StudentClassAssignment::where('student_id', $student->id)->count());
        $this->assertSame($oldPath, $student->fresh()->photo_path);
        $this->assertSame('FOTO-LAMA', Storage::disk('private')->get($oldPath));
    }

    /** @test */
    public function re_enrollment_gagal_setelah_file_tertulis_tidak_menghapus_foto_lama(): void
    {
        [$teacher, $rombel, $school] = $this->teacherScenario();
        $student = $this->existingStudentWithOldPhoto($school, '0051119005');
        $oldPath = $student->photo_path;

        $this->failAfterUpload(Student::class);
        $this->postStudent($teacher, $this->studentFields($rombel, '0051119005', UploadedFile::fake()->image('foto.jpg', 60, 60)))
            ->assertStatus(500);

        $this->assertSame(0, StudentClassAssignment::where('student_id', $student->id)->count());
        $this->assertSame($oldPath, $student->fresh()->photo_path);
        Storage::disk('private')->assertExists($oldPath);
    }

    /** @test */
    public function re_enrollment_berhasil_dengan_ekstensi_beda_menghapus_foto_lama_setelah_commit(): void
    {
        [$teacher, $rombel, $school] = $this->teacherScenario();
        $student = $this->existingStudentWithOldPhoto($school, '0051119006');
        $oldPath = $student->photo_path; // students/{id}.png

        $this->postStudent($teacher, $this->studentFields($rombel, '0051119006', UploadedFile::fake()->image('foto.jpg', 60, 60)))
            ->assertStatus(201);

        $this->assertSame("students/{$student->id}.jpg", $student->fresh()->photo_path);
        Storage::disk('private')->assertExists("students/{$student->id}.jpg");
        Storage::disk('private')->assertMissing($oldPath);
        $this->assertSame(1, StudentClassAssignment::where('student_id', $student->id)->count());
    }

    // ---------- create sekolah ----------

    protected function postSchool(array $fields)
    {
        $this->sa ??= $this->fxSuperAdmin();

        return $this->withHeaders(['Authorization' => 'Bearer ' . $this->fxToken($this->sa), 'Accept' => 'application/json'])
            ->post('/api/v1/schools', $fields);
    }

    /** @test */
    public function create_sekolah_dengan_logo_gagal_unggah_rollback_dan_retry_dengan_nama_sama_berhasil(): void
    {
        $fields = fn () => ['name' => 'SD Logo Uji', 'address' => 'Jl. Uji 1', 'logo' => UploadedFile::fake()->image('logo.png', 60, 60)];

        $this->failUploads();
        $this->postSchool($fields())->assertStatus(500);
        $this->assertSame(0, School::where('name', 'SD Logo Uji')->count());

        $this->restoreUploads();
        $this->postSchool($fields())->assertStatus(201);

        $school = School::where('name', 'SD Logo Uji')->firstOrFail();
        $this->assertSame("logos/{$school->id}.png", $school->logo_path);
        Storage::disk('public')->assertExists($school->logo_path);
    }

    /** @test */
    public function create_sekolah_file_logo_yang_sudah_tertulis_dihapus_bila_gagal_setelah_unggah(): void
    {
        $this->failAfterUpload(School::class);
        $this->postSchool(['name' => 'SD Yatim', 'logo' => UploadedFile::fake()->image('logo.png', 60, 60)])->assertStatus(500);

        $this->assertSame(0, School::where('name', 'SD Yatim')->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    /** @test */
    public function create_sekolah_tanpa_logo_tidak_berubah(): void
    {
        $this->failUploads();
        $this->postSchool(['name' => 'SD Tanpa Logo'])->assertStatus(201)->assertJsonPath('data.logo', null);
    }
}

class FlakyUploadService extends UploadService
{
    public static bool $fail = false;

    public function upload(UploadedFile $file, string $folder, int|string $identifier, ?string $oldPath = null, string $disk = 'public'): string
    {
        if (self::$fail) {
            throw new \RuntimeException('simulasi: unggah ke S3 gagal');
        }

        return parent::upload($file, $folder, $identifier, $oldPath, $disk);
    }
}
