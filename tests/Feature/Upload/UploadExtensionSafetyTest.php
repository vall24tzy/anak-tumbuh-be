<?php

namespace Tests\Feature\Upload;

use App\Exceptions\ApiException;
use App\Services\Upload\UploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSchoolFixtures;
use Tests\TestCase;

// F-05 - ekstensi file upload tidak boleh berasal dari nama klien.
//
// Bug: UploadService::upload() memakai getClientOriginalExtension(). Laravel hanya
// memblokir .php/.phtml/.phar (shouldBlockPhpUpload); ekstensi eksekusi lain
// (.pht, .phps, .inc, dll.) lolos validasi dan tersimpan apa adanya, mis.
// students/{id}.pht. SoT: Matrix v3.1 F-05 (AC), Task Board UPLOAD-001 (safe
// filename), API Contract §11/§17.
//
// Yang dikunci:
//   - ekstensi tersimpan hanya jpg/png/webp, ditentukan oleh KONTEN gambar
//   - nama file tetap {folder}/{identifier}.{ext} (system-generated)
//   - tidak ada file ber-ekstensi di luar allowlist di storage
//   - format di luar allowlist ditolak (fail-closed), tidak pernah disimpan
//   - jalur HTTP (foto Student: PATCH dan POST, logo School) mengikuti aturan yang sama
//
// Di luar scope: urutan delete->upload (F-08), batas dimensi/ukuran (F-07),
// akses foto private (F-06). F-06 (Stage 2B) sudah masuk: kasus HTTP foto Student kini
// diverifikasi di disk 'private' (disesuaikan dari 'public'); logo School tetap di
// 'public'; kasus level service memakai disk bawaan 'public'. Butuh ekstensi PHP `gd`
// dengan dukungan webp.
//
// STATUS EKSEKUSI: dijalankan di PHP + PostgreSQL; hasil terkini lihat docs/testing/BASELINE-STAGE-0C.md

class UploadExtensionSafetyTest extends TestCase
{
    use RefreshDatabase, CreatesSchoolFixtures;

    private const ALLOWED = ['jpg', 'png', 'webp'];

    /** @var string[] file sementara yang dibuat helper upload() */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('private');   // F-06: foto Student ada di disk privat, logo School di disk publik
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    // ---------- helper ----------

    /** Bytes gambar asli (bukan sekadar nama) untuk format tertentu. */
    protected function imageBytes(string $format): string
    {
        $image = imagecreatetruecolor(40, 40);

        ob_start();
        match ($format) {
            'jpeg' => imagejpeg($image),
            'png'  => imagepng($image),
            'webp' => imagewebp($image),
            'gif'  => imagegif($image),
        };
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    /**
     * Konten asli + nama klien bebas (sengaja tidak harus cocok dengan konten).
     * Memakai Illuminate\Http\UploadedFile NYATA (file temp di disk), bukan
     * UploadedFile::fake(): file fake menebak mime dari NAMA klien, sedangkan
     * upload sungguhan dideteksi dari KONTEN, jadi hanya yang nyata yang meniru
     * produksi untuk rule `image`/`mimes`.
     */
    protected function upload(string $clientName, string $format = 'jpeg'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'f05_');
        file_put_contents($path, $this->imageBytes($format));
        $this->tempFiles[] = $path;

        return new UploadedFile($path, $clientName, null, null, true);
    }

    protected function assertOnlyAllowedExtensionsOnDisk(): void
    {
        foreach (['public', 'private'] as $disk) {
            foreach (Storage::disk($disk)->allFiles() as $path) {
                $this->assertContains(
                    strtolower(pathinfo($path, PATHINFO_EXTENSION)),
                    self::ALLOWED,
                    "file ber-ekstensi di luar allowlist tersimpan di disk {$disk}: {$path}"
                );
            }
        }
    }

    /** Satu sekolah, dua rombel: Teacher A dan Student di rombel A. */
    protected function studentScenario(): array
    {
        $school = $this->fxSchool('SD Satu');
        $year = $this->fxAcademicYear($school);
        $rombelA = $this->fxRombel($school, $year, 'VII-A');

        $teacher = $this->fxTeacher($school, '2001');
        $this->fxTeacherAssignment($teacher->teacher, $rombelA);

        $studentUser = $this->fxStudent($school, '0051234567');
        $this->fxStudentAssignment($studentUser->student, $rombelA);

        return [$teacher, $studentUser->student, $rombelA];
    }

    protected function postMultipart(string $uri, ?string $token, array $fields)
    {
        $headers = ['Accept' => 'application/json'];

        if ($token) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        return $this->post($uri, $fields, $headers);
    }

    // ---------- level service: nama klien berbahaya diabaikan ----------

    /**
     * @test
     * @dataProvider dangerousClientNames
     */
    public function nama_klien_berbahaya_tetap_disimpan_dengan_ekstensi_allowlist_hasil_konten(string $clientName): void
    {
        $path = app(UploadService::class)->upload($this->upload($clientName, 'jpeg'), 'students', 7);

        $this->assertSame('students/7.jpg', $path);
        $this->assertSame(['students/7.jpg'], Storage::disk('public')->allFiles());
        $this->assertOnlyAllowedExtensionsOnDisk();
    }

    public static function dangerousClientNames(): array
    {
        return [
            '.php'      => ['x.php'],
            '.pht'      => ['x.pht'],
            '.phps'     => ['x.phps'],
            '.inc'      => ['x.inc'],
            '.phtml'    => ['x.phtml'],
            '.phar'     => ['x.phar'],
            '.JPG.php'  => ['x.JPG.php'],
            '.exe'      => ['x.exe'],
            'tanpa ext' => ['foto'],
        ];
    }

    /**
     * @test
     * @dataProvider contentDecidesExtension
     */
    public function ekstensi_ditentukan_konten_bukan_nama_klien(string $format, string $clientName, string $expectedExtension): void
    {
        $path = app(UploadService::class)->upload($this->upload($clientName, $format), 'logos', 3);

        $this->assertSame("logos/3.{$expectedExtension}", $path);
        Storage::disk('public')->assertExists($path);
        $this->assertOnlyAllowedExtensionsOnDisk();
    }

    public static function contentDecidesExtension(): array
    {
        return [
            'JPEG bernama .png'   => ['jpeg', 'foto.png', 'jpg'],
            'PNG bernama .jpg'    => ['png', 'foto.jpg', 'png'],
            'WEBP bernama .jpeg'  => ['webp', 'foto.jpeg', 'webp'],
            'JPEG bernama .jpeg'  => ['jpeg', 'foto.jpeg', 'jpg'],
            'PNG bernama .PNG'    => ['png', 'FOTO.PNG', 'png'],
        ];
    }

    /** @test */
    public function format_di_luar_allowlist_ditolak_fail_closed_dan_tidak_disimpan(): void
    {
        try {
            app(UploadService::class)->upload($this->upload('animasi.gif', 'gif'), 'students', 9);
            $this->fail('GIF seharusnya ditolak');
        } catch (ApiException $e) {
            $this->assertSame('validation_failed', $e->errorCode());
        }

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    // ---------- level HTTP ----------

    /** @test */
    public function patch_foto_student_dengan_nama_klien_pht_phps_inc_tersimpan_sebagai_jpg(): void
    {
        [$teacher, $student] = $this->studentScenario();
        $token = $this->fxToken($teacher);

        foreach (['x.pht', 'x.phps', 'x.inc'] as $clientName) {
            $this->postMultipart("/api/v1/students/{$student->id}", $token, [
                '_method' => 'PATCH',
                'photo'   => $this->upload($clientName, 'jpeg'),
            ])->assertStatus(200);

            $this->assertSame("students/{$student->id}.jpg", $student->fresh()->photo_path, "nama klien {$clientName}");
            $this->assertSame(["students/{$student->id}.jpg"], Storage::disk('private')->allFiles());
            $this->assertSame([], Storage::disk('public')->allFiles(), 'foto Student tidak boleh ada di disk publik');
        }

        $this->assertOnlyAllowedExtensionsOnDisk();
    }

    /** @test */
    public function patch_foto_student_nama_klien_php_phtml_phar_ditolak_validasi_dan_tidak_ada_file(): void
    {
        [$teacher, $student] = $this->studentScenario();
        $token = $this->fxToken($teacher);

        foreach (['x.php', 'x.phtml', 'x.phar', 'x.JPG.php'] as $clientName) {
            $this->postMultipart("/api/v1/students/{$student->id}", $token, [
                '_method' => 'PATCH',
                'photo'   => $this->upload($clientName, 'jpeg'),
            ])->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
        }

        $this->assertNull($student->fresh()->photo_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    /** @test */
    public function create_student_dengan_foto_nama_klien_pht_tersimpan_sebagai_jpg(): void
    {
        [$teacher, , $rombel] = $this->studentScenario();

        $response = $this->postMultipart('/api/v1/students', $this->fxToken($teacher), [
            'rombel_id' => $rombel->id,
            'name'      => 'Siswa Baru',
            'nisn'      => '0059999999',
            'gender'    => 'perempuan',
            'photo'     => $this->upload('foto.pht', 'jpeg'),
        ]);

        $response->assertStatus(201);

        $id = $response->json('data.id');
        $this->assertSame("students/{$id}.jpg", \App\Models\Student::find($id)->photo_path);
        $this->assertSame(["students/{$id}.jpg"], Storage::disk('private')->allFiles());
        $this->assertSame([], Storage::disk('public')->allFiles(), 'foto Student tidak boleh ada di disk publik');
    }

    /** @test */
    public function create_school_dengan_logo_nama_klien_pht_tersimpan_sebagai_png(): void
    {
        $token = $this->fxToken($this->fxSuperAdmin());

        $response = $this->postMultipart('/api/v1/schools', $token, [
            'name'    => 'SD Berlogo',
            'address' => 'Jl. Mawar 2',
            'logo'    => $this->upload('logo.pht', 'png'),
        ]);

        $response->assertStatus(201);

        $id = $response->json('data.id');
        $this->assertDatabaseHas('schools', ['id' => $id, 'logo_path' => "logos/{$id}.png"]);
        $this->assertStringEndsWith("logos/{$id}.png", $response->json('data.logo'));
        $this->assertSame(["logos/{$id}.png"], Storage::disk('public')->allFiles());
    }
}
