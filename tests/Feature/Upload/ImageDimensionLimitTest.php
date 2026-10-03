<?php

namespace Tests\Feature\Upload;

use App\Http\Requests\Student\UpdateStudentRequest;
use App\Http\Requests\School\CreateSchoolRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tests\Concerns\CreatesSchoolFixtures;
use Tests\Concerns\MakesImageUploads;
use Tests\TestCase;

// F-07 - batas dimensi gambar 4096x4096 px (inklusif) dan 2 MB, dicek server-side lewat rule
// `dimensions` (membaca header gambar, bukan decode penuh) pada SEMUA FormRequest ber-rule `image`:
// CreateStudentRequest, UpdateStudentRequest, CreateSchoolRequest (hasil grep 'image' di
// app/Http/Requests; tidak ada FormRequest Headmaster/Teacher dengan rule image).
// SoT: Matrix v3.1 F-07 (keputusan FINAL), Task Board UPLOAD-001.
//
// Yang dikunci:
//   - tepat 4096 di lebar atau tinggi -> diterima; 4097 di salah satu sisi -> 422 (field photo/logo)
//   - gambar 4096x4096 sungguhan lolos rule dan 4097x4096 ditolak (diuji di level rule dengan PNG palet
//     satu warna yang kecil, supaya tidak ada lonjakan memori)
//   - file > 2 MB tetap 422; gambar kecil tetap diterima
//   - ketiga request tercakup (Student update, Student create, School create)
//
// Catatan: kasus batas HTTP memakai 4096x100 dan 100x4096 (bukan 4096x1) karena scaleDown(500)
// pada gambar setipis 1 px menghasilkan tinggi 0.
//
// STATUS EKSEKUSI: dijalankan di PHP + PostgreSQL; hasil terkini lihat docs/testing/BASELINE-STAGE-0C.md

class ImageDimensionLimitTest extends TestCase
{
    use RefreshDatabase, CreatesSchoolFixtures, MakesImageUploads;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('private');
    }

    protected function tearDown(): void
    {
        $this->cleanupImageFiles();

        parent::tearDown();
    }

    protected function world(): array
    {
        $school = $this->fxSchool('SD Satu');
        $year = $this->fxAcademicYear($school);
        $rombel = $this->fxRombel($school, $year, 'VII-A');
        $teacher = $this->fxTeacher($school, '2001');
        $this->fxTeacherAssignment($teacher->teacher, $rombel);
        $studentUser = $this->fxStudent($school, '0051234567');
        $this->fxStudentAssignment($studentUser->student, $rombel);

        return [$teacher, $studentUser->student, $rombel];
    }

    protected function patchPhoto($student, $token, UploadedFile $photo)
    {
        return $this->post("/api/v1/students/{$student->id}", ['_method' => 'PATCH', 'photo' => $photo],
            ['Accept' => 'application/json', 'Authorization' => "Bearer {$token}"]);
    }

    // ---------- PATCH foto Student ----------

    /**
     * @test
     * @dataProvider acceptedSizes
     */
    public function ukuran_sampai_batas_inklusif_diterima(int $width, int $height): void
    {
        [$teacher, $student] = $this->world();

        $this->patchPhoto($student, $this->fxToken($teacher), $this->imageUpload('foto.png', 'png', $width, $height))
            ->assertStatus(200);
    }

    public static function acceptedSizes(): array
    {
        return [
            'lebar tepat 4096'  => [4096, 100],
            'tinggi tepat 4096' => [100, 4096],
            'gambar kecil'      => [100, 100],
        ];
    }

    /**
     * @test
     * @dataProvider rejectedSizes
     */
    public function lebih_dari_4096_di_salah_satu_sisi_ditolak_422(int $width, int $height): void
    {
        [$teacher, $student] = $this->world();

        $response = $this->patchPhoto($student, $this->fxToken($teacher), $this->imageUpload('foto.png', 'png', $width, $height));

        $response->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
        $this->assertSame('photo', $response->json('error.details.0.field'));
        $this->assertNull($student->fresh()->photo_path);
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    public static function rejectedSizes(): array
    {
        return [
            'lebar 4097'  => [4097, 100],
            'tinggi 4097' => [100, 4097],
        ];
    }

    /** @test */
    public function file_lebih_dari_2_mb_tetap_ditolak_422(): void
    {
        [$teacher, $student] = $this->world();

        $big = UploadedFile::fake()->image('besar.jpg', 100, 100)->size(3000);

        $this->patchPhoto($student, $this->fxToken($teacher), $big)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    // ---------- batas pada level rule (gambar 4096x4096 sungguhan) ----------

    /** @test */
    public function gambar_4096_kali_4096_lolos_rule_dan_4097_kali_4096_ditolak(): void
    {
        foreach ([
            'photo student' => (new UpdateStudentRequest())->rules()['photo'],
            'logo school'   => (new CreateSchoolRequest())->rules()['logo'],
        ] as $label => $rules) {
            $ok = Validator::make(['f' => $this->paletteImageUpload(4096, 4096)], ['f' => $rules]);
            $this->assertTrue($ok->passes(), "{$label}: 4096x4096 harus diterima: " . json_encode($ok->errors()->all()));

            $tooWide = Validator::make(['f' => $this->paletteImageUpload(4097, 4096)], ['f' => $rules]);
            $this->assertTrue($tooWide->fails(), "{$label}: 4097x4096 harus ditolak");

            $tooTall = Validator::make(['f' => $this->paletteImageUpload(4096, 4097)], ['f' => $rules]);
            $this->assertTrue($tooTall->fails(), "{$label}: 4096x4097 harus ditolak");
        }
    }

    // ---------- request lain yang punya rule image ----------

    /** @test */
    public function create_student_menolak_foto_4097_dan_menerima_4096(): void
    {
        [$teacher, , $rombel] = $this->world();
        $token = $this->fxToken($teacher);
        $headers = ['Accept' => 'application/json', 'Authorization' => "Bearer {$token}"];

        $rejected = $this->post('/api/v1/students', [
            'rombel_id' => $rombel->id, 'name' => 'Siswa A', 'nisn' => '0058888881', 'gender' => 'laki-laki',
            'photo' => $this->imageUpload('foto.png', 'png', 4097, 100),
        ], $headers);

        $rejected->assertStatus(422);
        $this->assertSame('photo', $rejected->json('error.details.0.field'));
        $this->assertDatabaseMissing('students', ['nisn' => '0058888881']);

        $this->post('/api/v1/students', [
            'rombel_id' => $rombel->id, 'name' => 'Siswa B', 'nisn' => '0058888882', 'gender' => 'laki-laki',
            'photo' => $this->imageUpload('foto.png', 'png', 4096, 100),
        ], $headers)->assertStatus(201);
    }

    /** @test */
    public function create_school_menolak_logo_4097_dan_menerima_4096(): void
    {
        $token = $this->fxToken($this->fxSuperAdmin('sa_dim'));
        $headers = ['Accept' => 'application/json', 'Authorization' => "Bearer {$token}"];

        $rejected = $this->post('/api/v1/schools', [
            'name' => 'SD Logo Besar', 'logo' => $this->imageUpload('logo.png', 'png', 100, 4097),
        ], $headers);

        $rejected->assertStatus(422);
        $this->assertSame('logo', $rejected->json('error.details.0.field'));
        $this->assertDatabaseMissing('schools', ['name' => 'SD Logo Besar']);

        $this->post('/api/v1/schools', [
            'name' => 'SD Logo Pas', 'logo' => $this->imageUpload('logo.png', 'png', 100, 4096),
        ], $headers)->assertStatus(201);
    }
}
