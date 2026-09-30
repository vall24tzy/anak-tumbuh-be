<?php

namespace Tests\Feature\Auth;

use App\Jobs\ProcessStudentImportJob;
use App\Jobs\ProcessTeacherImportJob;
use App\Models\Headmaster;
use App\Models\ImportBatch;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\CreatesSchoolFixtures;
use Tests\TestCase;

// STAGE 0-B RESIDUAL F-14 - job import di queue worker tidak boleh memberi
// authority aktif kepada pemilik batch yang sudah inactive.
//
// Akar masalah: ProcessTeacherImportJob / ProcessStudentImportJob memanggil
// auth('api')->login($actingUser) tanpa melewati middleware `jwt`
// (AuthenticateActiveSession), jadi status lifecycle tidak pernah dicek.
// Fix: trait App\Jobs\Concerns\ActsAsActiveBatchOwner (User::isLifecycleActive()).
//
// Peran pemilik batch menurut SoT/route: import Guru = Headmaster (Matrix #6),
// import Siswa = Teacher (Matrix #8). Skenario "silang" (inactive Teacher di job
// Guru, inactive Headmaster di job Siswa) tidak bisa terjadi lewat HTTP, tapi
// tetap diuji sebagai defense-in-depth: gate memeriksa status, bukan asumsi role.
//
// Job dijalankan langsung (dispatchSync) pada baris ImportBatch yang dibuat
// manual, meniru kondisi "diantre saat masih aktif, dieksekusi setelah inactive".
// Status profil di-flip LANGSUNG (assignment Teacher sengaja dibiarkan aktif)
// supaya yang teruji murni gate job, bukan pemeriksaan scope sekunder.
//
// STATUS EKSEKUSI: belum dijalankan (tidak ada PHP/Postgres di lingkungan
// penyusun patch). Jalankan: php artisan test --filter=ImportJobAuthorityTest

class ImportJobAuthorityTest extends TestCase
{
    use RefreshDatabase, CreatesSchoolFixtures;

    /** File .xlsx asli di disk default (root storage/app), path relatif seperti $file->store('imports'). */
    protected function storeXlsx(array $rows, array $headings): string
    {
        $export = new class($rows, $headings) implements FromArray, WithHeadings {
            public function __construct(private array $rows, private array $headings) {}
            public function array(): array { return $this->rows; }
            public function headings(): array { return $this->headings; }
        };

        $path = 'imports/' . uniqid('job_', true) . '.xlsx';
        Excel::store($export, $path, 'local');

        return $path;
    }

    protected function teacherFile(): string
    {
        return $this->storeXlsx([
            ['Guru Satu', '199501012024121001', 'laki-laki'],
            ['Guru Dua', '199502022024121002', 'perempuan'],
        ], ['Nama Lengkap', 'NIP', 'Gender']);
    }

    protected function studentFile(): string
    {
        return $this->storeXlsx([
            ['Siswa Baru', '0059999999', 'laki-laki'],
        ], ['Nama Lengkap', 'NISN', 'Gender']);
    }

    protected function makeBatch(string $type, ?int $createdBy): ImportBatch
    {
        return ImportBatch::create(['type' => $type, 'status' => 'pending', 'created_by' => $createdBy]);
    }

    /** Sekolah + tahun ajaran aktif + rombel + Teacher dengan assignment aktif. */
    protected function teacherWorld(): array
    {
        $school = $this->fxSchool();
        $rombel = $this->fxRombel($school, $this->fxAcademicYear($school), 'VII-A');
        $teacher = $this->fxTeacher($school, '2001');
        $this->fxTeacherAssignment($teacher->teacher, $rombel);

        return [$school, $rombel, $teacher];
    }

    protected function assertJobAborted(ImportBatch $batch, string $path, int $usersBefore): void
    {
        $batch->refresh();

        $this->assertSame('failed', $batch->status);
        $this->assertSame(0, (int) $batch->success);
        $this->assertSame($usersBefore, User::count(), 'tidak boleh ada user baru yang dibuat job');
        $this->assertFalse(Storage::disk('local')->exists($path), 'file upload (PII) harus dihapus saat job dibatalkan');
        $this->assertNull(auth('api')->user(), 'job tidak boleh meninggalkan authority (tidak ada login)');
    }

    // ---------- ProcessTeacherImportJob (pemilik batch = Headmaster) ----------

    /** @test */
    public function job_import_guru_headmaster_aktif_tetap_berjalan_normal(): void
    {
        $school = $this->fxSchool();
        $hm = $this->fxHeadmaster($school, '1001');
        $path = $this->teacherFile();
        $batch = $this->makeBatch('teacher', $hm->id);

        ProcessTeacherImportJob::dispatchSync($batch->id, $path);

        $batch->refresh();
        $this->assertSame('done', $batch->status);
        $this->assertSame(2, (int) $batch->total);
        $this->assertSame(2, (int) $batch->success);
        $this->assertSame(0, (int) $batch->failed);
        $this->assertSame(2, Teacher::where('school_id', $school->id)->count());
        $this->assertDatabaseHas('teachers', ['nip' => '199501012024121001', 'school_id' => $school->id, 'status' => 'active']);
        $this->assertDatabaseHas('users', ['username' => '199502022024121002', 'role' => 'teacher']);
        $this->assertFalse(Storage::disk('local')->exists($path), 'file dihapus setelah sukses (perilaku lama dipertahankan)');
    }

    /** @test */
    public function job_import_guru_headmaster_inactive_tidak_memperoleh_authority(): void
    {
        $school = $this->fxSchool();
        $hm = $this->fxHeadmaster($school, '1001');
        $path = $this->teacherFile();
        $batch = $this->makeBatch('teacher', $hm->id); // diantre saat Headmaster masih aktif

        Headmaster::where('user_id', $hm->id)->update(['status' => 'inactive']); // mis. digantikan suksesi
        $usersBefore = User::count();

        ProcessTeacherImportJob::dispatchSync($batch->id, $path);

        $this->assertJobAborted($batch, $path, $usersBefore);
        $this->assertSame(0, Teacher::count());
        $this->assertDatabaseHas('headmasters', ['user_id' => $hm->id, 'status' => 'inactive']); // histori utuh
    }

    /** @test */
    public function job_import_guru_pemilik_batch_hilang_gagal_tertutup(): void
    {
        $this->fxSchool();
        $path = $this->teacherFile();
        $batch = $this->makeBatch('teacher', null); // created_by NULL (user dihapus)
        $usersBefore = User::count();

        ProcessTeacherImportJob::dispatchSync($batch->id, $path);

        $this->assertJobAborted($batch, $path, $usersBefore);
        $this->assertSame(0, Teacher::count());
    }

    /** @test */
    public function job_import_guru_pemilik_teacher_inactive_juga_ditolak_defense_in_depth(): void
    {
        [, , $teacher] = $this->teacherWorld();
        $path = $this->teacherFile();
        $batch = $this->makeBatch('teacher', $teacher->id);

        Teacher::where('user_id', $teacher->id)->update(['status' => 'inactive']);
        $usersBefore = User::count();

        ProcessTeacherImportJob::dispatchSync($batch->id, $path);

        $this->assertJobAborted($batch, $path, $usersBefore);
        $this->assertSame(1, Teacher::count(), 'hanya Teacher fixture; tidak ada Teacher hasil import');
    }

    // ---------- ProcessStudentImportJob (pemilik batch = Teacher) ----------

    /** @test */
    public function job_import_siswa_teacher_aktif_tetap_berjalan_normal(): void
    {
        [$school, $rombel, $teacher] = $this->teacherWorld();
        $path = $this->studentFile();
        $batch = $this->makeBatch('student', $teacher->id);

        ProcessStudentImportJob::dispatchSync($batch->id, $path, $rombel->id);

        $batch->refresh();
        $this->assertSame('done', $batch->status);
        $this->assertSame(1, (int) $batch->success);
        $this->assertSame(0, (int) $batch->failed);
        $this->assertDatabaseHas('students', ['nisn' => '0059999999', 'school_id' => $school->id, 'status' => 'active']);
        $this->assertDatabaseHas('student_class_assignments', ['rombel_id' => $rombel->id, 'status' => 'active']);
        $this->assertFalse(Storage::disk('local')->exists($path), 'file dihapus setelah sukses (perilaku lama dipertahankan)');
    }

    /** @test */
    public function job_import_siswa_teacher_inactive_tidak_memperoleh_authority(): void
    {
        [, $rombel, $teacher] = $this->teacherWorld();
        $path = $this->studentFile();
        $batch = $this->makeBatch('student', $teacher->id); // diantre saat Teacher masih aktif

        // Hanya status profil yang di-flip; assignment aktif sengaja DIBIARKAN supaya
        // yang teruji adalah gate lifecycle job, bukan cabang "tidak ada assignment".
        Teacher::where('user_id', $teacher->id)->update(['status' => 'inactive']);
        $usersBefore = User::count();

        ProcessStudentImportJob::dispatchSync($batch->id, $path, $rombel->id);

        $this->assertJobAborted($batch, $path, $usersBefore);
        $this->assertSame(0, Student::count());
        $this->assertDatabaseHas('teachers', ['user_id' => $teacher->id, 'status' => 'inactive']); // histori utuh
    }

    /** @test */
    public function job_import_siswa_pemilik_headmaster_inactive_juga_ditolak_defense_in_depth(): void
    {
        [$school, $rombel] = $this->teacherWorld();
        $hm = $this->fxHeadmaster($school, '1001');
        $path = $this->studentFile();
        $batch = $this->makeBatch('student', $hm->id);

        Headmaster::where('user_id', $hm->id)->update(['status' => 'inactive']);
        $usersBefore = User::count();

        ProcessStudentImportJob::dispatchSync($batch->id, $path, $rombel->id);

        $this->assertJobAborted($batch, $path, $usersBefore);
        $this->assertSame(0, Student::count());
    }

    /** @test */
    public function job_import_siswa_pemilik_batch_hilang_gagal_tertutup(): void
    {
        [, $rombel] = $this->teacherWorld();
        $path = $this->studentFile();
        $batch = $this->makeBatch('student', null);
        $usersBefore = User::count();

        ProcessStudentImportJob::dispatchSync($batch->id, $path, $rombel->id);

        $this->assertJobAborted($batch, $path, $usersBefore);
        $this->assertSame(0, Student::count());
    }
}
