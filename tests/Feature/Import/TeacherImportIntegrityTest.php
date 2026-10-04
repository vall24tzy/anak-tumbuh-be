<?php

namespace Tests\Feature\Import;

use App\Exceptions\ApiException;
use App\Models\ImportBatch;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Import\ImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Excel as ExcelType;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\CreatesSchoolFixtures;
use Tests\TestCase;
use ZipArchive;

// Stage 4A / Import Teacher.
//
// F-33: hanya .xlsx (API Contract §18, OD-12); .xls -> 422. Berlaku juga untuk preview Student.
// F-34: file tidak bisa diparse -> 422 import_file_unparseable; header kolom wajib hilang/salah
//       ketik -> SATU error 422 import_header_invalid (bukan error per baris); tidak ada batch dan
//       tidak ada Teacher yang dibuat.
// F-35: NIP kembar ANTAR-BARIS dalam file -> baris pertama diproses, baris berikutnya gagal dengan
//       code duplicate_in_file yang menyebut baris asal (bukan unexpected_error generik).
// F-36: NIP dan nama di-trim sebelum validasi dan simpan; akun bisa login dengan NIP bersih.
//
// F-38 (bentuk response async >100 baris) SENGAJA tidak dicakup: ditahan karena E-1 (lihat laporan).
//
// STATUS EKSEKUSI: ditulis di sandbox tanpa PHP/PostgreSQL; hasil run lihat laporan patch.

class TeacherImportIntegrityTest extends TestCase
{
    use RefreshDatabase, CreatesSchoolFixtures;

    protected User $headmaster;
    protected \App\Models\School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = $this->fxSchool();
        $this->headmaster = $this->fxHeadmaster($this->school, '1001');
    }

    protected function makeFile(array $rows, array $headings = ['Nama Lengkap', 'NIP', 'Gender'], string $type = ExcelType::XLSX, string $ext = 'xlsx'): UploadedFile
    {
        $export = new class($rows, $headings) implements FromArray, WithHeadings {
            public function __construct(private array $rows, private array $headings) {}
            public function array(): array { return $this->rows; }
            public function headings(): array { return $this->headings; }
        };

        $path = 'test-imports/' . uniqid('guru_', true) . '.' . $ext;
        Excel::store($export, $path, 'local', $type);

        return new UploadedFile(storage_path('app/' . $path), 'guru.' . $ext, null, null, true);
    }

    protected function postImport(UploadedFile $file)
    {
        return $this->withHeaders([
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $this->fxToken($this->headmaster),
        ])->post('/api/v1/teachers/import', ['file' => $file]);
    }

    // ---------- F-33 ----------

    /** @test */
    public function f33_file_xls_ditolak_422_dan_tidak_ada_yang_dibuat(): void
    {
        $file = $this->makeFile([['Guru Xls', '5001', 'laki-laki']], type: ExcelType::XLS, ext: 'xls');

        $this->postImport($file)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->assertSame(0, ImportBatch::count());
        $this->assertDatabaseMissing('teachers', ['nip' => '5001']);
    }

    /** @test */
    public function f33_file_xlsx_tetap_diterima_200(): void
    {
        $this->postImport($this->makeFile([['Guru Xlsx', '5002', 'laki-laki']]))
            ->assertStatus(200)
            ->assertJsonPath('data.successful_rows', 1);
    }

    /** @test */
    public function f33_preview_student_juga_menolak_xls_422(): void
    {
        $teacher = $this->fxTeacher($this->school, '2001');
        $file = $this->makeFile([['Siswa Xls', '0051110001', 'laki-laki']], ['Nama Lengkap', 'NISN', 'Gender'], ExcelType::XLS, 'xls');

        $this->withHeaders(['Accept' => 'application/json', 'Authorization' => 'Bearer ' . $this->fxToken($teacher)])
            ->post('/api/v1/student-imports/preview', ['file' => $file])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    // ---------- F-34 ----------

    /** @test */
    public function f34_header_nip_diganti_nama_lain_422_struktural_satu_error_bukan_per_baris(): void
    {
        $file = $this->makeFile(
            [['Guru A', '6001', 'laki-laki'], ['Guru B', '6002', 'perempuan'], ['Guru C', '6003', 'laki-laki']],
            ['Nama Lengkap', 'Nomor Induk', 'Gender']
        );

        $response = $this->postImport($file)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'import_header_invalid');

        $this->assertCount(1, $response->json('error.details'));
        $this->assertSame('nip', $response->json('error.details.0.field'));
        $this->assertSame(0, ImportBatch::count());
        $this->assertSame(0, Teacher::where('nip', 'like', '600%')->count());
    }

    /** @test */
    public function f34_header_gender_hilang_422_struktural(): void
    {
        $file = $this->makeFile([['Guru A', '6101']], ['Nama Lengkap', 'NIP']);

        $this->postImport($file)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'import_header_invalid')
            ->assertJsonPath('error.details.0.field', 'gender');

        $this->assertSame(0, ImportBatch::count());
    }

    /** @test */
    public function f34_file_xlsx_rusak_422_import_file_unparseable_bukan_500(): void
    {
        // xlsx asli, lalu isi worksheet-nya dirusak: tetap terdeteksi sebagai xlsx oleh `mimes`
        // (struktur zip utuh) tetapi tidak bisa diparse.
        $good = $this->makeFile([['Guru A', '6201', 'laki-laki']]);
        $broken = storage_path('app/test-imports/' . uniqid('rusak_', true) . '.xlsx');
        copy($good->getRealPath(), $broken);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($broken) === true);
        $zip->addFromString('xl/worksheets/sheet1.xml', '<<<bukan xml>>>');
        $zip->close();

        $file = new UploadedFile($broken, 'rusak.xlsx', null, null, true);

        $this->postImport($file)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'import_file_unparseable');

        $this->assertSame(0, ImportBatch::count());
        $this->assertDatabaseMissing('teachers', ['nip' => '6201']);
    }

    /** @test */
    public function f34_parse_rows_pada_path_yang_bukan_xlsx_melempar_apiexception_422(): void
    {
        // Jalur job queue memberi path string; deterministik tanpa `mimes`.
        $path = storage_path('app/test-imports/' . uniqid('sampah_', true) . '.xlsx');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, 'ini bukan xlsx');

        try {
            (new ImportService(['nip' => 'nip'], ['nip' => ['required']]))->parseRows($path);
            $this->fail('ApiException tidak dilempar');
        } catch (ApiException $e) {
            $this->assertSame('import_file_unparseable', $e->errorCode());
            $this->assertSame(422, $e->status());
        }
    }

    /** @test */
    public function f34_header_lengkap_tetap_berjalan_normal(): void
    {
        $this->postImport($this->makeFile([['Guru A', '6301', 'laki-laki']]))
            ->assertStatus(200)
            ->assertJsonPath('data.successful_rows', 1)
            ->assertJsonPath('data.rejected_rows', 0);
    }

    // ---------- F-35 ----------

    /** @test */
    public function f35_nip_kembar_dalam_file_baris_kedua_gagal_dengan_code_jelas_baris_pertama_sukses(): void
    {
        $file = $this->makeFile([
            ['Guru A', '7001', 'laki-laki'],   // baris 2
            ['Guru B', '7001', 'perempuan'],   // baris 3, kembar dengan baris 2
            ['Guru C', '7002', 'laki-laki'],   // baris 4, tetap diproses
        ]);

        $response = $this->postImport($file)->assertStatus(200);

        $this->assertSame(3, $response->json('data.total_rows'));
        $this->assertSame(2, $response->json('data.successful_rows'));
        $this->assertSame(1, $response->json('data.rejected_rows'));

        $error = $response->json('data.errors.0');
        $this->assertSame(3, $error['row']);
        $this->assertSame('nip', $error['field']);
        $this->assertSame('duplicate_in_file', $error['code']);
        $this->assertStringContainsString('baris 2', $error['message']);

        $this->assertSame(1, Teacher::where('nip', '7001')->count());
        $this->assertSame('Guru A', Teacher::where('nip', '7001')->value('fullname'));
        $this->assertSame(1, Teacher::where('nip', '7002')->count());
    }

    /** @test */
    public function f35_baris_pertama_tidak_valid_tidak_membuat_baris_kedua_dianggap_kembar(): void
    {
        $file = $this->makeFile([
            ['Guru A', '7101', 'bukan-gender'], // baris 2: ditolak validasi, tidak pernah diproses
            ['Guru B', '7101', 'perempuan'],    // baris 3: NIP sama tapi satu-satunya yang valid
        ]);

        $response = $this->postImport($file)->assertStatus(200);

        $this->assertSame(1, $response->json('data.successful_rows'));
        $this->assertSame(1, $response->json('data.rejected_rows'));
        $this->assertSame(2, $response->json('data.errors.0.row'));
        $this->assertSame('validation_failed', $response->json('data.errors.0.code'));
        $this->assertSame('Guru B', Teacher::where('nip', '7101')->value('fullname'));
    }

    // ---------- F-36 ----------

    /** @test */
    public function f36_nip_dan_nama_dengan_spasi_tepi_tersimpan_ter_trim_dan_bisa_login(): void
    {
        $this->postImport($this->makeFile([['  Guru Trim  ', '  8001  ', 'laki-laki']]))
            ->assertStatus(200)
            ->assertJsonPath('data.successful_rows', 1);

        $this->assertDatabaseHas('teachers', ['nip' => '8001', 'fullname' => 'Guru Trim']);
        $this->assertDatabaseHas('users', ['username' => '8001', 'role' => 'teacher']);
        $this->assertDatabaseMissing('users', ['username' => '  8001  ']);

        // Password awal = NIP bersih; login dengan NIP bersih berhasil.
        $this->postJson('/api/v1/auth/login/staff', ['username' => '8001', 'password' => '8001'])
            ->assertStatus(200);
    }

    /** @test */
    public function f36_trim_dilakukan_sebelum_cek_duplikat_nip_dengan_spasi_dianggap_kembar(): void
    {
        $file = $this->makeFile([
            ['Guru A', '8101', 'laki-laki'],
            ['Guru B', ' 8101 ', 'perempuan'],
        ]);

        $response = $this->postImport($file)->assertStatus(200);

        $this->assertSame(1, $response->json('data.successful_rows'));
        $this->assertSame('duplicate_in_file', $response->json('data.errors.0.code'));
        $this->assertSame(1, Teacher::where('nip', '8101')->count());
    }
}
