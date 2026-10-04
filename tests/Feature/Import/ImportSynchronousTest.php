<?php

namespace Tests\Feature\Import;

use App\Models\ImportBatch;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\School\TeacherImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Mockery\MockInterface;
use RuntimeException;
use Tests\Concerns\CreatesSchoolFixtures;
use Tests\TestCase;

// F-38 (keputusan FINAL pemilik): import SELALU sinkron untuk semua ukuran file sampai batas 1.000
// baris. Tidak ada queue/async, tidak ada cabang berdasarkan jumlah baris, response selalu HTTP 200
// dengan lima field kontrak (total_rows, processed_rows, successful_rows, rejected_rows, errors[])
// plus batch_id; tidak ada `status:pending`. Berlaku untuk Teacher dan Student import.
//
// Test ini juga menggantikan nilai pengaman dari ImportJobAuthorityTest (F-14 residual) dan
// ImportJobFailureTest (F-37) yang dihapus bersama job-nya: pemilik inactive ditolak di jalur
// request, dan exception di tengah import tidak menahan batch di 'processing'.
//
// STATUS EKSEKUSI: ditulis di sandbox tanpa PHP/PostgreSQL; hasil run lihat laporan patch.

class ImportSynchronousTest extends TestCase
{
    use RefreshDatabase, CreatesSchoolFixtures;

    protected function makeFile(array $rows, array $headings): UploadedFile
    {
        $export = new class($rows, $headings) implements FromArray, WithHeadings {
            public function __construct(private array $rows, private array $headings) {}
            public function array(): array { return $this->rows; }
            public function headings(): array { return $this->headings; }
        };

        $path = 'test-imports/' . uniqid('sync_', true) . '.xlsx';
        Excel::store($export, $path, 'local');

        return new UploadedFile(storage_path('app/' . $path), 'import.xlsx', null, null, true);
    }

    protected function teacherRows(int $n, int $start = 100000): array
    {
        $rows = [];
        for ($i = 0; $i < $n; $i++) {
            $rows[] = ['Guru ' . ($start + $i), (string) ($start + $i), $i % 2 ? 'perempuan' : 'laki-laki'];
        }

        return $rows;
    }

    protected function studentRows(int $n): array
    {
        $rows = [];
        for ($i = 0; $i < $n; $i++) {
            $rows[] = ['Siswa ' . $i, '0051' . str_pad((string) $i, 6, '0', STR_PAD_LEFT), $i % 2 ? 'perempuan' : 'laki-laki'];
        }

        return $rows;
    }

    protected function postTeacherImport(User $headmaster, UploadedFile $file)
    {
        return $this->withHeaders(['Accept' => 'application/json', 'Authorization' => 'Bearer ' . $this->fxToken($headmaster)])
            ->post('/api/v1/teachers/import', ['file' => $file]);
    }

    protected function assertSynchronousShape($response, int $total): void
    {
        $response->assertStatus(200)
            ->assertJsonPath('data.total_rows', $total)
            ->assertJsonPath('data.processed_rows', $total)
            ->assertJsonPath('data.successful_rows', $total)
            ->assertJsonPath('data.rejected_rows', 0)
            ->assertJsonPath('data.errors', [])
            ->assertJsonMissingPath('data.status');

        $this->assertIsInt($response->json('data.batch_id'));
    }

    // ---------- Teacher: ukuran yang dulu memicu async ----------

    /** @test */
    public function teacher_101_baris_diproses_langsung_di_response_yang_sama(): void
    {
        $hm = $this->fxHeadmaster($this->fxSchool(), '1001');

        $response = $this->postTeacherImport($hm, $this->makeFile($this->teacherRows(101), ['Nama Lengkap', 'NIP', 'Gender']));

        $this->assertSynchronousShape($response, 101);
        $this->assertSame(101, Teacher::where('nip', '>=', '100000')->count());

        $batch = ImportBatch::findOrFail($response->json('data.batch_id'));
        $this->assertSame('done', $batch->status);
        $this->assertSame(101, (int) $batch->success);
    }

    /** @test */
    public function teacher_150_baris_diproses_langsung_dan_nilainya_sama_dengan_status_batch(): void
    {
        $hm = $this->fxHeadmaster($this->fxSchool(), '1001');

        $response = $this->postTeacherImport($hm, $this->makeFile($this->teacherRows(150), ['Nama Lengkap', 'NIP', 'Gender']));
        $this->assertSynchronousShape($response, 150);

        // Endpoint status (F-32) tetap bisa membaca hasil batch yang sudah selesai.
        $this->apiGet('/api/v1/teachers/import/status/' . $response->json('data.batch_id'), $this->fxToken($hm))
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'done')
            ->assertJsonPath('data.successful_rows', 150);
    }

    /** @test */
    public function teacher_500_baris_diproses_langsung(): void
    {
        $hm = $this->fxHeadmaster($this->fxSchool(), '1001');

        $this->assertSynchronousShape(
            $this->postTeacherImport($hm, $this->makeFile($this->teacherRows(500), ['Nama Lengkap', 'NIP', 'Gender'])),
            500
        );
        $this->assertSame(500, Teacher::where('nip', '>=', '100000')->count());
    }

    /** @test */
    public function teacher_di_atas_100_baris_baris_bermasalah_tetap_dilaporkan_di_response_yang_sama(): void
    {
        $hm = $this->fxHeadmaster($this->fxSchool(), '1001');
        $rows = $this->teacherRows(120);
        $rows[10][1] = $rows[5][1];     // NIP kembar antar-baris (F-35), baris file 12 vs 7
        $rows[20][2] = 'bukan-gender';  // validasi baris (baris file 22)

        $response = $this->postTeacherImport($hm, $this->makeFile($rows, ['Nama Lengkap', 'NIP', 'Gender']))->assertStatus(200);

        $this->assertSame(120, $response->json('data.total_rows'));
        $this->assertSame(118, $response->json('data.successful_rows'));
        $this->assertSame(2, $response->json('data.rejected_rows'));
        $codes = collect($response->json('data.errors'))->pluck('code', 'row')->all();
        $this->assertSame('duplicate_in_file', $codes[12]);
        $this->assertSame('validation_failed', $codes[22]);
    }

    // ---------- Student: jalur yang sama ----------

    /** @test */
    public function student_101_baris_diproses_langsung_di_response_yang_sama(): void
    {
        $school = $this->fxSchool();
        $rombel = $this->fxRombel($school, $this->fxAcademicYear($school), 'VII-A');
        $teacher = $this->fxTeacher($school, '2001');
        $this->fxTeacherAssignment($teacher->teacher, $rombel);

        $response = $this->withHeaders(['Accept' => 'application/json', 'Authorization' => 'Bearer ' . $this->fxToken($teacher)])
            ->post('/api/v1/student-imports/commit', [
                'file'      => $this->makeFile($this->studentRows(101), ['Nama Lengkap', 'NISN', 'Gender']),
                'rombel_id' => $rombel->id,
            ]);

        $this->assertSynchronousShape($response, 101);
        $this->assertSame(101, Student::where('nisn', 'like', '0051%')->count());
        $this->assertSame('done', ImportBatch::findOrFail($response->json('data.batch_id'))->status);
    }

    // ---------- Batas 1.000 baris tetap berlaku ----------

    /** @test */
    public function lebih_dari_1000_baris_tetap_422_dan_tidak_diproses_sama_sekali(): void
    {
        $hm = $this->fxHeadmaster($this->fxSchool(), '1001');

        $this->postTeacherImport($hm, $this->makeFile($this->teacherRows(1001), ['Nama Lengkap', 'NIP', 'Gender']))
            ->assertStatus(422);

        $this->assertSame(0, ImportBatch::count());
        $this->assertSame(0, Teacher::where('nip', '>=', '100000')->count());
    }

    // ---------- Pengganti pengaman dari test job yang dihapus ----------

    /** @test */
    public function headmaster_inactive_tidak_bisa_import_403_tanpa_batch_dan_tanpa_user_baru(): void
    {
        $school = $this->fxSchool();
        $hm = $this->fxHeadmaster($school, '1001');
        $token = $this->fxToken($hm);
        $hm->headmaster->update(['status' => 'inactive']);
        $usersBefore = User::count();

        $this->withHeaders(['Accept' => 'application/json', 'Authorization' => 'Bearer ' . $token])
            ->post('/api/v1/teachers/import', ['file' => $this->makeFile($this->teacherRows(3), ['Nama Lengkap', 'NIP', 'Gender'])])
            ->assertStatus(403);

        $this->assertSame($usersBefore, User::count());
        $this->assertSame(0, ImportBatch::where('status', 'done')->count());
    }

    /** @test */
    public function teacher_inactive_tidak_bisa_commit_import_siswa_403_tanpa_student_baru(): void
    {
        $school = $this->fxSchool();
        $rombel = $this->fxRombel($school, $this->fxAcademicYear($school), 'VII-A');
        $teacher = $this->fxTeacher($school, '2001');
        $this->fxTeacherAssignment($teacher->teacher, $rombel);
        $token = $this->fxToken($teacher);
        $teacher->teacher->update(['status' => 'inactive']);

        $this->withHeaders(['Accept' => 'application/json', 'Authorization' => 'Bearer ' . $token])
            ->post('/api/v1/student-imports/commit', [
                'file'      => $this->makeFile($this->studentRows(3), ['Nama Lengkap', 'NISN', 'Gender']),
                'rombel_id' => $rombel->id,
            ])
            ->assertStatus(403);

        $this->assertSame(0, Student::where('nisn', 'like', '0051%')->count());
        $this->assertSame(0, ImportBatch::where('status', 'done')->count());
    }

    /** @test */
    public function exception_di_tengah_import_menandai_batch_failed_bukan_tertahan_di_processing(): void
    {
        $hm = $this->fxHeadmaster($this->fxSchool(), '1001');

        $this->mock(TeacherImportService::class, function (MockInterface $mock) {
            $mock->shouldReceive('countRows')->andReturn(3);
            $mock->shouldReceive('commit')->andThrow(new RuntimeException('boom'));
        });

        $this->postTeacherImport($hm, $this->makeFile($this->teacherRows(3), ['Nama Lengkap', 'NIP', 'Gender']))
            ->assertStatus(500);

        $batch = ImportBatch::firstOrFail();
        $this->assertSame('failed', $batch->status);
        $this->assertNotSame('processing', $batch->status);
    }
}
