<?php

namespace Tests\Feature\School;

use App\Exports\TeacherImportTemplateExport;
use App\Models\Headmaster;
use App\Models\School;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

// IMPORT-002 — API Contract v1.2 §15/§28, Dokumen v4.0 §3/§4.2.
// Setup pakai Model::create() berantai, konsisten dengan task lain.
//
// STATUS EKSEKUSI: belum dijalankan di sandbox ini (tidak ada Postgres live,
// juga belum pernah dicoba generate file xlsx asli via Maatwebsite\Excel di
// sandbox ini). Jalankan manual: php artisan test --filter=TeacherImportTest

class TeacherImportTest extends TestCase
{
    use RefreshDatabase;

    protected School $schoolA;
    protected User $headmasterUserA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolA = School::create(['name' => 'SD Satu']);

        $this->headmasterUserA = User::create([
            'role'     => 'headmaster',
            'username' => '1001',
            'password' => 'secret',
        ]);

        Headmaster::create([
            'user_id'   => $this->headmasterUserA->id,
            'school_id' => $this->schoolA->id,
            'fullname'  => 'Kepala Sekolah Satu',
            'nip'       => '1001',
            'gender'    => 'laki-laki',
            'status'    => 'active',
        ]);
    }

    protected function authHeader(User $user): array
    {
        $token = auth('api')->login($user);
        return ['Authorization' => "Bearer {$token}"];
    }

    /**
     * Bikin file .xlsx asli di storage sementara dari array baris, dibungkus
     * jadi UploadedFile supaya bisa dipakai post()->attach() ala multipart.
     */
    protected function makeXlsx(array $rows, array $headings = ['Nama Lengkap', 'NIP', 'Gender']): UploadedFile
    {
        $export = new class($rows, $headings) implements FromArray, WithHeadings {
            public function __construct(private array $rows, private array $headings) {}
            public function array(): array { return $this->rows; }
            public function headings(): array { return $this->headings; }
        };

        $path = 'test-imports/' . uniqid('guru_', true) . '.xlsx';
        Excel::store($export, $path, 'local');

        $fullPath = storage_path('app/' . $path);

        return new UploadedFile($fullPath, 'guru.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    /** @test */
    public function template_tidak_punya_kolom_classroom_email(): void
    {
        $export = new TeacherImportTemplateExport();

        $this->assertSame(['Nama Lengkap', 'NIP', 'Gender'], $export->headings());
    }

    /** @test */
    public function import_baris_valid_berhasil_create_user_dan_teacher(): void
    {
        $file = $this->makeXlsx([
            ['Guru Baru', '199501012024121001', 'laki-laki'],
        ]);

        $response = $this->withHeaders($this->authHeader($this->headmasterUserA))
            ->post('/api/v1/teachers/import', ['file' => $file]);

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('data.successful_rows'));
        $this->assertSame(0, $response->json('data.rejected_rows'));

        $this->assertDatabaseHas('teachers', [
            'nip'       => '199501012024121001',
            'school_id' => $this->schoolA->id,
            'status'    => 'active',
        ]);
        $this->assertDatabaseHas('users', [
            'username' => '199501012024121001',
            'role'     => 'teacher',
        ]);

        // Password tidak pernah bocor di response.
        $response->assertJsonMissingPath('data.errors.0.password');
    }

    /** @test */
    public function import_nip_sudah_ada_ditolak_baris_tanpa_gagalkan_file(): void
    {
        $existingUser = User::create(['role' => 'teacher', 'username' => '199001011111', 'password' => 'secret']);
        Teacher::create([
            'user_id' => $existingUser->id, 'school_id' => $this->schoolA->id,
            'fullname' => 'Guru Lama', 'nip' => '199001011111',
            'gender' => 'perempuan', 'status' => 'active',
        ]);

        $file = $this->makeXlsx([
            ['Guru Baru Valid', '199502022024121002', 'laki-laki'],
            ['Guru Duplikat', '199001011111', 'perempuan'],
        ]);

        $response = $this->withHeaders($this->authHeader($this->headmasterUserA))
            ->post('/api/v1/teachers/import', ['file' => $file]);

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('data.successful_rows'));
        $this->assertSame(1, $response->json('data.rejected_rows'));
        $this->assertSame('nip', $response->json('data.errors.0.field'));
        $this->assertSame('validation_failed', $response->json('data.errors.0.code'));

        // Baris valid tetap masuk meski baris lain gagal (partial processing).
        $this->assertDatabaseHas('teachers', ['nip' => '199502022024121002']);
    }

    /** @test */
    public function import_gender_invalid_dilaporkan_validation_failed(): void
    {
        $file = $this->makeXlsx([
            ['Guru Gender Salah', '199503032024121003', 'pria'],
        ]);

        $response = $this->withHeaders($this->authHeader($this->headmasterUserA))
            ->post('/api/v1/teachers/import', ['file' => $file]);

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('data.rejected_rows'));
        $this->assertSame('gender', $response->json('data.errors.0.field'));
        $this->assertSame('validation_failed', $response->json('data.errors.0.code'));
    }

    /** @test */
    public function import_nama_kosong_dilaporkan_validation_failed(): void
    {
        $file = $this->makeXlsx([
            ['', '199504042024121004', 'laki-laki'],
        ]);

        $response = $this->withHeaders($this->authHeader($this->headmasterUserA))
            ->post('/api/v1/teachers/import', ['file' => $file]);

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('data.rejected_rows'));
        $this->assertSame('name', $response->json('data.errors.0.field'));
    }

    /** @test */
    public function super_admin_tidak_bisa_akses_import_403(): void
    {
        $saUser = User::create(['role' => 'super_admin', 'username' => 'admin1', 'password' => 'secret']);

        $file = $this->makeXlsx([['Guru Satu', '199505052024121005', 'laki-laki']]);

        $response = $this->withHeaders($this->authHeader($saUser))
            ->post('/api/v1/teachers/import', ['file' => $file]);

        $response->assertStatus(403);
    }

    /** @test */
    public function teacher_tidak_bisa_akses_import_403(): void
    {
        $teacherUser = User::create(['role' => 'teacher', 'username' => '199506062024121006', 'password' => 'secret']);
        Teacher::create([
            'user_id' => $teacherUser->id, 'school_id' => $this->schoolA->id,
            'fullname' => 'Guru Coba Import', 'nip' => '199506062024121006',
            'gender' => 'laki-laki', 'status' => 'active',
        ]);

        $file = $this->makeXlsx([['Guru Satu', '199507072024121007', 'laki-laki']]);

        $response = $this->withHeaders($this->authHeader($teacherUser))
            ->post('/api/v1/teachers/import', ['file' => $file]);

        $response->assertStatus(403);
    }

    /** @test */
    public function import_headmaster_lain_tidak_ikut_masuk_sekolah_a(): void
    {
        $schoolB = School::create(['name' => 'SD Dua']);
        $headmasterUserB = User::create(['role' => 'headmaster', 'username' => '1002', 'password' => 'secret']);
        Headmaster::create([
            'user_id' => $headmasterUserB->id, 'school_id' => $schoolB->id,
            'fullname' => 'Kepala Sekolah Dua', 'nip' => '1002',
            'gender' => 'perempuan', 'status' => 'active',
        ]);

        $file = $this->makeXlsx([['Guru Sekolah B', '199508082024121008', 'laki-laki']]);

        $response = $this->withHeaders($this->authHeader($headmasterUserB))
            ->post('/api/v1/teachers/import', ['file' => $file]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('teachers', [
            'nip'       => '199508082024121008',
            'school_id' => $schoolB->id,
        ]);
        // Bukan school_id Headmaster A, walau ada di request/file manapun —
        // school_id tidak pernah dibaca dari input, jadi tidak ada jalur
        // untuk salah nyasar ke sekolah lain.
        $this->assertDatabaseMissing('teachers', [
            'nip'       => '199508082024121008',
            'school_id' => $this->schoolA->id,
        ]);
    }
}