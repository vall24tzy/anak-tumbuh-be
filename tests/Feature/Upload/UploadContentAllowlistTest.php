<?php

namespace Tests\Feature\Upload;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSchoolFixtures;
use Tests\TestCase;

// SECURITY-001 butir 6 - allowlist upload berbasis KONTEN (bukan nama/ekstensi klien), foto Student
// tetap di disk privat dan logo School di disk publik. Melengkapi UploadExtensionSafetyTest (F-05) yang
// memakai gambar asli dengan nama berbahaya; di sini kebalikannya: nama "aman", KONTEN berbahaya/di luar allowlist.
//
// STATUS EKSEKUSI: ditulis di sandbox tanpa PHP; jalankan `php artisan test --filter=UploadContentAllowlistTest`.

class UploadContentAllowlistTest extends TestCase
{
    use RefreshDatabase, CreatesSchoolFixtures;

    /** @var string[] */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('private');
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    protected function fileWith(string $bytes, string $clientName): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'sec_');
        file_put_contents($path, $bytes);
        $this->tempFiles[] = $path;

        return new UploadedFile($path, $clientName, null, null, true);
    }

    protected function gifBytes(): string
    {
        $image = imagecreatetruecolor(20, 20);
        ob_start();
        imagegif($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    protected function postLogo(string $token, UploadedFile $file)
    {
        return $this->post('/api/v1/schools', ['name' => 'SD Uji ' . uniqid(), 'address' => 'Jl. Uji', 'logo' => $file], [
            'Accept' => 'application/json', 'Authorization' => "Bearer {$token}",
        ]);
    }

    public static function disguisedContents(): array
    {
        return [
            'kode PHP berkedok .jpg'  => ["<?php /* bukan gambar */ echo 'a'; ?>", 'foto.jpg'],
            'kode PHP berkedok .png'  => ["<?php echo 'x'; ?>", 'logo.png'],
            'HTML/JS berkedok .webp'  => ["<html><script>alert(1)</script></html>", 'gambar.webp'],
            'SVG dengan script'       => ['<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'logo.png'],
            'teks biasa berkedok .jpg' => ['bukan gambar', 'foto.jpg'],
        ];
    }

    /**
     * @test
     * @dataProvider disguisedContents
     */
    public function logo_school_dengan_konten_bukan_gambar_ditolak_422_dan_tidak_tersimpan(string $bytes, string $clientName): void
    {
        $token = $this->fxToken($this->fxSuperAdmin());

        $this->postLogo($token, $this->fileWith($bytes, $clientName))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    /** @test */
    public function logo_school_gif_asli_bernama_png_ditolak_karena_gif_di_luar_allowlist(): void
    {
        $token = $this->fxToken($this->fxSuperAdmin());

        $this->postLogo($token, $this->fileWith($this->gifBytes(), 'logo.png'))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    /**
     * @test
     * @dataProvider disguisedContents
     */
    public function foto_student_dengan_konten_bukan_gambar_ditolak_422_dan_tidak_tersimpan(string $bytes, string $clientName): void
    {
        $school = $this->fxSchool();
        $rombel = $this->fxRombel($school, $this->fxAcademicYear($school), 'VII-A');
        $teacher = $this->fxTeacher($school, '2001');
        $this->fxTeacherAssignment($teacher->teacher, $rombel);

        $this->post('/api/v1/students', [
            'rombel_id' => $rombel->id, 'name' => 'Siswa Uji', 'nisn' => '0057654321', 'gender' => 'laki-laki',
            'photo'     => $this->fileWith($bytes, $clientName),
        ], ['Accept' => 'application/json', 'Authorization' => 'Bearer ' . $this->fxToken($teacher)])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->assertSame([], Storage::disk('private')->allFiles());
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertDatabaseMissing('students', ['nisn' => '0057654321']);
    }

    /** @test */
    public function gambar_png_asli_foto_student_di_disk_privat_dan_logo_school_di_disk_publik(): void
    {
        $image = imagecreatetruecolor(30, 30);
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        $school = $this->fxSchool();
        $rombel = $this->fxRombel($school, $this->fxAcademicYear($school), 'VII-A');
        $teacher = $this->fxTeacher($school, '2001');
        $this->fxTeacherAssignment($teacher->teacher, $rombel);

        $student = $this->post('/api/v1/students', [
            'rombel_id' => $rombel->id, 'name' => 'Siswa Sah', 'nisn' => '0059876543', 'gender' => 'perempuan',
            'photo'     => $this->fileWith($png, 'foto.png'),
        ], ['Accept' => 'application/json', 'Authorization' => 'Bearer ' . $this->fxToken($teacher)]);

        $student->assertStatus(201);
        $studentId = $student->json('data.id');
        $this->assertSame(["students/{$studentId}.png"], Storage::disk('private')->allFiles());
        $this->assertSame([], Storage::disk('public')->allFiles());

        $logo = $this->postLogo($this->fxToken($this->fxSuperAdmin()), $this->fileWith($png, 'logo.png'));
        $logo->assertStatus(201);
        $logoId = $logo->json('data.id');
        $this->assertSame(["logos/{$logoId}.png"], Storage::disk('public')->allFiles());
        $this->assertSame(["students/{$studentId}.png"], Storage::disk('private')->allFiles());
    }
}
