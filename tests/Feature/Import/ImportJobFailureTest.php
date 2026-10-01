<?php

namespace Tests\Feature\Import;

use App\Jobs\ProcessStudentImportJob;
use App\Jobs\ProcessTeacherImportJob;
use App\Models\ImportBatch;
use App\Models\User;
use App\Services\School\TeacherImportService;
use App\Services\Student\StudentImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Concerns\CreatesSchoolFixtures;
use Tests\TestCase;

// F-37 - job import async yang melempar exception harus meninggalkan batch dalam status
// terminal (`failed`), BUKAN `processing` selamanya, dan file upload (berisi PII) harus terhapus.
//
// Akar masalah: ProcessTeacherImportJob dan ProcessStudentImportJob (pola identik) tidak punya
// penanganan kegagalan; hanya jalur sukses yang memanggil Storage::delete(). Fix: method
// `failed()` (trait ClosesFailedImportBatch), yang dipanggil Laravel saat job gagal permanen.
//
// Test memakai dispatchSync: untuk job ShouldQueue itu berjalan lewat SyncQueue, yang memanggil
// `failed()` lalu MELEMPAR ULANG exception (sama seperti worker mencatat job gagal), jadi test
// menangkap exception-nya. Service di-mock agar kegagalan deterministik; file korup asli
// sengaja tidak dipakai karena pelaporannya diatur ulang di F-34.
//
// Perilaku yang dikunci tidak berubah: sukses -> `done` + file terhapus; abort lifecycle
// (residual F-14) -> `failed` + file terhapus + service tidak dipanggil. ImportJobAuthorityTest
// tidak diubah dan tetap menjadi kunci jalur nyata (tanpa mock).
//
// Di luar scope: kontrak response import (F-38), penanganan exception di jalur sinkron (<=100
// baris) di controller, dan jendela balapan inactive-saat-berjalan (sisa F-14).
//
// STATUS EKSEKUSI: dijalankan di PHP + PostgreSQL; hasil terkini lihat docs/testing/BASELINE-STAGE-0C.md

class ImportJobFailureTest extends TestCase
{
    use RefreshDatabase, CreatesSchoolFixtures;

    private const FILE = 'imports/f37_upload.xlsx';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(); // disk default ('local') palsu; job memakai Storage::path()/delete() pada disk ini
    }

    protected function putUpload(): string
    {
        Storage::put(self::FILE, 'berisi PII: NIP, nama');
        $this->assertTrue(Storage::exists(self::FILE), 'precondition: file upload ada');

        return self::FILE;
    }

    protected function makeBatch(string $type, ?int $createdBy, string $status = 'pending', array $extra = []): ImportBatch
    {
        return ImportBatch::create(array_merge(['type' => $type, 'status' => $status, 'created_by' => $createdBy], $extra));
    }

    /** Pemilik batch aktif: Headmaster untuk job Guru, Teacher untuk job Siswa. */
    protected function activeOwner(string $type): User
    {
        $school = $this->fxSchool('SD ' . $type); // nama unik: helper ini dipanggil berulang dalam satu test

        return $type === 'teacher' ? $this->fxHeadmaster($school, '1001') : $this->fxTeacher($school, '2001');
    }

    protected function inactiveOwner(string $type): User
    {
        $owner = $this->activeOwner($type);

        if ($type === 'teacher') {
            $owner->headmaster()->update(['status' => 'inactive']);
        } else {
            $owner->teacher()->update(['status' => 'inactive']);
        }

        return $owner;
    }

    protected function runJob(string $type, int $batchId, string $path): void
    {
        $type === 'teacher'
            ? ProcessTeacherImportJob::dispatchSync($batchId, $path)
            : ProcessStudentImportJob::dispatchSync($batchId, $path, 999);
    }

    protected function successResult(): array
    {
        return [
            'total_rows' => 3, 'processed_rows' => 3, 'successful_rows' => 2, 'rejected_rows' => 1,
            'errors' => [['row' => 3, 'field' => 'nip', 'code' => 'validation_failed', 'message' => 'x']],
        ];
    }

    /** Mock service: commit() dipanggil tepat sekali dan menjalankan $behavior (return array atau throw). */
    protected function mockService(string $type, \Closure $behavior): void
    {
        $class = $type === 'teacher' ? TeacherImportService::class : StudentImportService::class;

        $this->mock($class, function ($mock) use ($behavior) {
            $mock->shouldReceive('commit')->once()->andReturnUsing($behavior);
        });
    }

    protected function assertFailedClosed(ImportBatch $batch, string $path, int $usersBefore): void
    {
        $batch->refresh();

        $this->assertSame('failed', $batch->status, 'batch harus terminal, bukan processing');
        $this->assertSame([], $batch->failed_rows);
        $this->assertFalse(Storage::exists($path), 'file upload (PII) harus terhapus');
        $this->assertSame($usersBefore, User::count(), 'tidak ada user baru');
    }

    // ---------- 1. exception di tengah proses (inti F-37) ----------

    /** @test */
    public function job_guru_exception_di_proses_menandai_batch_failed_dan_menghapus_file(): void
    {
        $owner = $this->activeOwner('teacher');
        $path = $this->putUpload();
        $batch = $this->makeBatch('teacher', $owner->id);
        $usersBefore = User::count();
        $statusSaatJalan = null;

        $this->mockService('teacher', function () use ($batch, &$statusSaatJalan) {
            $statusSaatJalan = ImportBatch::find($batch->id)->status;
            throw new RuntimeException('gagal di tengah proses');
        });

        try {
            $this->runJob('teacher', $batch->id, $path);
            $this->fail('exception harus tetap dilempar ke caller/worker agar kegagalan tercatat');
        } catch (RuntimeException $e) {
            $this->assertSame('gagal di tengah proses', $e->getMessage());
        }

        $this->assertSame('processing', $statusSaatJalan, 'precondition: pada saat gagal, batch memang berstatus processing');
        $this->assertFailedClosed($batch, $path, $usersBefore);
        $this->assertSame([0, 0, 0], [(int) $batch->total, (int) $batch->success, (int) $batch->failed], 'hitungan tidak disentuh');
    }

    /** @test */
    public function job_siswa_exception_di_proses_menandai_batch_failed_dan_menghapus_file(): void
    {
        $owner = $this->activeOwner('student');
        $path = $this->putUpload();
        $batch = $this->makeBatch('student', $owner->id);
        $usersBefore = User::count();
        $statusSaatJalan = null;

        $this->mockService('student', function () use ($batch, &$statusSaatJalan) {
            $statusSaatJalan = ImportBatch::find($batch->id)->status;
            throw new RuntimeException('gagal di tengah proses');
        });

        try {
            $this->runJob('student', $batch->id, $path);
            $this->fail('exception harus tetap dilempar ke caller/worker agar kegagalan tercatat');
        } catch (RuntimeException $e) {
            $this->assertSame('gagal di tengah proses', $e->getMessage());
        }

        $this->assertSame('processing', $statusSaatJalan, 'precondition: pada saat gagal, batch memang berstatus processing');
        $this->assertFailedClosed($batch, $path, $usersBefore);
        $this->assertSame([0, 0, 0], [(int) $batch->total, (int) $batch->success, (int) $batch->failed], 'hitungan tidak disentuh');
    }

    // ---------- 2. jalur sukses tidak berubah ----------

    /** @test */
    public function job_guru_dan_siswa_sukses_tetap_done_dengan_hitungan_dan_file_terhapus(): void
    {
        foreach (['teacher', 'student'] as $type) {
            $owner = $this->activeOwner($type);
            $path = $this->putUpload();
            $batch = $this->makeBatch($type, $owner->id);

            $this->mockService($type, fn () => $this->successResult());
            $this->runJob($type, $batch->id, $path);

            $batch->refresh();
            $this->assertSame('done', $batch->status, $type);
            $this->assertSame([3, 2, 1], [(int) $batch->total, (int) $batch->success, (int) $batch->failed], $type);
            $this->assertSame($this->successResult()['errors'], $batch->failed_rows, $type);
            $this->assertFalse(Storage::exists($path), "file terhapus setelah sukses ({$type})");
        }
    }

    // ---------- 3. abort lifecycle (residual F-14) tidak berubah ----------

    /** @test */
    public function job_guru_dan_siswa_pemilik_inactive_tetap_failed_tanpa_memanggil_service(): void
    {
        foreach (['teacher', 'student'] as $type) {
            $owner = $this->inactiveOwner($type);
            $path = $this->putUpload();
            $batch = $this->makeBatch($type, $owner->id);
            $usersBefore = User::count();

            $class = $type === 'teacher' ? TeacherImportService::class : StudentImportService::class;
            $this->mock($class, fn ($mock) => $mock->shouldNotReceive('commit'));

            $this->runJob($type, $batch->id, $path);

            $this->assertFailedClosed($batch, $path, $usersBefore);
            $this->assertNull(auth('api')->user(), "tidak ada login ({$type})");
        }
    }

    // ---------- 4. failed() sebagai pengaman ----------

    /** @test */
    public function failed_tidak_menurunkan_batch_done_tetapi_tetap_menghapus_file(): void
    {
        foreach ([ProcessTeacherImportJob::class => 'teacher', ProcessStudentImportJob::class => 'student'] as $jobClass => $type) {
            $owner = $this->activeOwner($type);
            $path = $this->putUpload();
            $errors = [['row' => 3, 'field' => 'nip', 'code' => 'validation_failed', 'message' => 'x']];
            $batch = $this->makeBatch($type, $owner->id, 'done', ['total' => 5, 'success' => 4, 'failed' => 1, 'failed_rows' => $errors]);

            $job = $type === 'teacher' ? new $jobClass($batch->id, $path) : new $jobClass($batch->id, $path, 999);
            $job->failed(new RuntimeException('exception setelah hasil tercatat'));

            $batch->refresh();
            $this->assertSame('done', $batch->status, "batch done tidak boleh diturunkan ({$type})");
            $this->assertSame([5, 4, 1], [(int) $batch->total, (int) $batch->success, (int) $batch->failed], $type);
            $this->assertSame($errors, $batch->failed_rows, $type);
            $this->assertFalse(Storage::exists($path), "file tetap dihapus ({$type})");
        }
    }

    /** @test */
    public function failed_dengan_batch_hilang_tetap_menghapus_file_dan_tidak_error(): void
    {
        foreach ([ProcessTeacherImportJob::class => 'teacher', ProcessStudentImportJob::class => 'student'] as $jobClass => $type) {
            $path = $this->putUpload();

            $job = $type === 'teacher' ? new $jobClass(987654321, $path) : new $jobClass(987654321, $path, 999);
            $job->failed(new RuntimeException('batch sudah tidak ada'));

            $this->assertFalse(Storage::exists($path), "file dihapus walau batch hilang ({$type})");
        }
    }

    /** @test */
    public function failed_tanpa_argumen_exception_tidak_melempar_type_error(): void
    {
        // Job::fail() tanpa argumen (kegagalan manual) memanggil failed(null).
        $owner = $this->activeOwner('teacher');
        $path = $this->putUpload();
        $batch = $this->makeBatch('teacher', $owner->id, 'processing');

        (new ProcessTeacherImportJob($batch->id, $path))->failed(null);

        $this->assertSame('failed', $batch->fresh()->status);
        $this->assertFalse(Storage::exists($path));
    }
}
