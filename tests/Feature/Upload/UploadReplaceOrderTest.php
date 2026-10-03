<?php

namespace Tests\Feature\Upload;

use App\Exceptions\ApiException;
use App\Services\Student\StudentService;
use App\Services\Upload\UploadService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\Concerns\CreatesGamificationFixtures;
use Tests\TestCase;
use Throwable;

// F-08 - replace file upload tidak boleh kehilangan file lama bila file baru gagal dibuat.
//
// Bug: UploadService::upload() menghapus $oldPath DULU, baru decode -> encode -> simpan. File rusak,
// format tidak didukung (fail-closed F-05), atau penyimpanan gagal meninggalkan photo_path yang
// menggantung ke file yang sudah terhapus. Fix: decode/encode/simpan dulu, hapus file lama SESUDAH
// file baru tersimpan, dan hasil put() diperiksa (disk `public` memakai `throw => false`, jadi
// kegagalan put() berupa return false, bukan exception).
//
// Jebakan yang dikunci: nama file deterministik ({folder}/{identifier}.{ext}). Bila ekstensi lama
// dan baru sama, path-nya identik; menghapus "file lama" setelah menyimpan justru menghapus file yang
// baru disimpan.
//
// SoT: Matrix v3.1 F-08 (acceptance: upload gagal -> file lama dan *_path lama tidak berubah; upload
// sukses -> file lama terhapus; test kegagalan); Task Board UPLOAD-001.
//
// Di luar scope: batas dimensi (F-07), foto Student private (F-06), dan update field lain pada
// PATCH /students/{id} yang sudah tersimpan sebelum upload (pola partial-failure serupa F-04).
//
// Test ini terikat ke Storage::fake('public') dan jalur students/{id}.{ext}; F-06 (Stage 2B lanjutan):
// hanya test pemanggil StudentService yang dipindah ke disk 'private', test level UploadService tetap 'public',
// jangan dianggap regresi F-08.
//
// STATUS EKSEKUSI: dijalankan di PHP + PostgreSQL; hasil terkini lihat docs/testing/BASELINE-STAGE-0C.md

class UploadReplaceOrderTest extends TestCase
{
    use RefreshDatabase, CreatesGamificationFixtures;

    /** @var string[] file sementara yang dibuat helper */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('private');   // F-06: foto Student ada di disk privat
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        Mockery::close();
        parent::tearDown();
    }

    // ---------- helper ----------

    /** Bytes gambar asli untuk format tertentu. */
    protected function imageBytes(string $format): string
    {
        $image = imagecreatetruecolor(40, 40);

        ob_start();
        match ($format) {
            'jpeg' => imagejpeg($image),
            'png'  => imagepng($image),
            'gif'  => imagegif($image),
        };
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    /** UploadedFile NYATA (file temp di disk), seperti di produksi; bukan UploadedFile::fake(). */
    protected function uploadOf(string $bytes, string $clientName): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'f08_');
        file_put_contents($path, $bytes);
        $this->tempFiles[] = $path;

        return new UploadedFile($path, $clientName, null, null, true);
    }

    protected function image(string $format, string $clientName): UploadedFile
    {
        return $this->uploadOf($this->imageBytes($format), $clientName);
    }

    protected function corrupt(): UploadedFile
    {
        return $this->uploadOf('ini bukan gambar sama sekali', 'rusak.jpg');
    }

    protected function service(): UploadService
    {
        return app(UploadService::class);
    }

    protected function putOld(string $path, string $content): void
    {
        Storage::disk('public')->put($path, $content);
        Storage::disk('public')->assertExists($path);
    }

    // ---------- sukses ----------

    /** @test */
    public function replace_sukses_menyimpan_file_baru_lalu_menghapus_file_lama(): void
    {
        $this->putOld('students/7.png', 'FILE-LAMA');

        $path = $this->service()->upload($this->image('jpeg', 'baru.jpg'), 'students', 7, 'students/7.png');

        $this->assertSame('students/7.jpg', $path);
        Storage::disk('public')->assertExists('students/7.jpg');
        Storage::disk('public')->assertMissing('students/7.png');
        $this->assertSame(['students/7.jpg'], Storage::disk('public')->allFiles());
    }

    /** @test */
    public function replace_dengan_ekstensi_sama_menimpa_file_dan_tidak_menghapus_file_baru(): void
    {
        $this->putOld('students/7.jpg', 'FILE-LAMA');

        $path = $this->service()->upload($this->image('jpeg', 'baru.jpg'), 'students', 7, 'students/7.jpg');

        $this->assertSame('students/7.jpg', $path);
        Storage::disk('public')->assertExists('students/7.jpg');

        $content = Storage::disk('public')->get('students/7.jpg');
        $this->assertNotSame('FILE-LAMA', $content, 'file harus berisi gambar baru');
        $this->assertTrue(str_starts_with($content, "\xFF\xD8"), 'isi harus JPEG baru (penanda SOI)');
        $this->assertSame(['students/7.jpg'], Storage::disk('public')->allFiles());
    }

    // ---------- gagal: file lama tidak boleh disentuh ----------

    /** @test */
    public function decode_gagal_pada_file_rusak_tidak_mengubah_file_lama(): void
    {
        $this->putOld('students/7.png', 'FILE-LAMA');
        $threw = false;

        try {
            $this->service()->upload($this->corrupt(), 'students', 7, 'students/7.png');
        } catch (Throwable $e) {
            $threw = true;
        }

        $this->assertTrue($threw, 'file rusak seharusnya gagal di-decode');
        Storage::disk('public')->assertExists('students/7.png');
        $this->assertSame('FILE-LAMA', Storage::disk('public')->get('students/7.png'));
        $this->assertSame(['students/7.png'], Storage::disk('public')->allFiles());
    }

    /** @test */
    public function format_di_luar_allowlist_ditolak_dan_file_lama_tetap_ada(): void
    {
        $this->putOld('students/9.png', 'FILE-LAMA');

        try {
            $this->service()->upload($this->image('gif', 'animasi.gif'), 'students', 9, 'students/9.png');
            $this->fail('GIF seharusnya ditolak');
        } catch (ApiException $e) {
            $this->assertSame('validation_failed', $e->errorCode());
        }

        $this->assertSame('FILE-LAMA', Storage::disk('public')->get('students/9.png'));
        $this->assertSame(['students/9.png'], Storage::disk('public')->allFiles());
    }

    /** @test */
    public function penyimpanan_gagal_tidak_menghapus_file_lama_dan_tidak_mengembalikan_path(): void
    {
        // Disk `public` memakai throw=false: put() gagal = return false. Disk dimock supaya
        // kegagalan itu deterministik; delete() tidak boleh dipanggil sama sekali.
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('put')->once()->with('students/7.jpg', Mockery::type('string'))->andReturn(false);
        $disk->shouldNotReceive('delete');
        $disk->shouldNotReceive('exists');
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);

        $threw = false;

        try {
            $this->service()->upload($this->image('jpeg', 'baru.jpg'), 'students', 7, 'students/7.png');
        } catch (RuntimeException $e) {
            $threw = true;
        }

        $this->assertTrue($threw, 'put() yang gagal harus menjadi exception, bukan path ke file yang tidak ada');
    }

    // ---------- pemanggil: path lama di database tidak berubah ----------

    /** @test */
    public function update_foto_student_yang_gagal_membiarkan_photo_path_dan_file_lama_utuh(): void
    {
        [$school, $year] = $this->makeSchool();
        $rombel = $this->makeRombel($school, $year, 'VII-A', 7);
        $teacher = $this->makeTeacher($school, $year, $rombel);
        $student = $this->makeStudent($school, $year, $rombel, 0);

        // F-06: pemanggil StudentService memakai disk privat, jadi berkas lama ditaruh di sana.
        Storage::disk('private')->put("students/{$student->id}.png", 'FILE-LAMA');
        $student->update(['photo_path' => "students/{$student->id}.png"]);

        auth('api')->setUser($teacher);
        $threw = false;

        try {
            app(StudentService::class)->update($student->id, ['photo' => $this->corrupt()]);
        } catch (Throwable $e) {
            $threw = true;
        }

        $this->assertTrue($threw);
        $this->assertSame("students/{$student->id}.png", $student->fresh()->photo_path, 'photo_path lama tidak boleh berubah');
        $this->assertSame('FILE-LAMA', Storage::disk('private')->get("students/{$student->id}.png"));
    }
}
