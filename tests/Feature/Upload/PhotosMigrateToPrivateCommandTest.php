<?php

namespace Tests\Feature\Upload;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSchoolFixtures;
use Tests\TestCase;

// F-06 (migrasi data) - php artisan photos:migrate-to-private memindahkan foto Student yang
// terlanjur ada di disk publik ke disk privat dan menghapus salinan publiknya. Skema path tidak
// berubah (photo_path tetap students/{id}.{ext}); hanya disk-nya yang berbeda.
//
// Yang dikunci:
//   - berkas legacy pindah ke private dengan isi identik dan HILANG dari public (tidak ada dua salinan)
//   - hasil dilaporkan per baris (DIPINDAH / SUDAH-PRIVAT / TIDAK-ADA), bukan diam-diam
//   - photo_path di database tidak berubah; logo School (folder logos/) tidak tersentuh
//   - berkas yatim di public/students/ ikut dipindahkan (tidak dibiarkan publik)
//   - idempoten: dijalankan dua kali tidak merusak apa pun
//   - --dry-run hanya melaporkan
//   - bila salinan privat sudah ada, privat dipertahankan dan salinan publik dibuang
//
// STATUS EKSEKUSI: dijalankan di PHP + PostgreSQL; hasil terkini lihat docs/testing/BASELINE-STAGE-0C.md

class PhotosMigrateToPrivateCommandTest extends TestCase
{
    use RefreshDatabase, CreatesSchoolFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('private');
    }

    protected function studentWithPhotoRow(string $nisn, ?string $path): Student
    {
        $student = $this->fxStudent($this->fxSchool("SD {$nisn}"), $nisn)->student;
        $student->update(['photo_path' => $path]);

        return $student;
    }

    /** @test */
    public function berkas_legacy_pindah_ke_private_hilang_dari_public_dan_dilaporkan_per_baris(): void
    {
        $a = $this->studentWithPhotoRow('0050000001', null);
        $b = $this->studentWithPhotoRow('0050000002', null);
        $a->update(['photo_path' => "students/{$a->id}.jpg"]);
        $b->update(['photo_path' => "students/{$b->id}.png"]);
        Storage::disk('public')->put("students/{$a->id}.jpg", 'ISI-FOTO-A');
        Storage::disk('public')->put("students/{$b->id}.png", 'ISI-FOTO-B');
        Storage::disk('public')->put('logos/1.png', 'LOGO-SCHOOL');

        $this->artisan('photos:migrate-to-private')
            ->expectsOutputToContain("DIPINDAH     student #{$a->id} students/{$a->id}.jpg")
            ->expectsOutputToContain("DIPINDAH     student #{$b->id} students/{$b->id}.png")
            ->expectsOutputToContain('dipindahkan: 2, sudah di private: 0, yatim dipindahkan: 0, berkas tidak ada: 0, gagal: 0')
            ->assertExitCode(0);

        $this->assertSame('ISI-FOTO-A', Storage::disk('private')->get("students/{$a->id}.jpg"));
        $this->assertSame('ISI-FOTO-B', Storage::disk('private')->get("students/{$b->id}.png"));
        $this->assertSame([], Storage::disk('public')->allFiles('students'), 'tidak boleh ada salinan foto Student di disk publik');
        Storage::disk('public')->assertMissing("students/{$a->id}.jpg");
        Storage::disk('public')->assertExists('logos/1.png');
        $this->assertSame('LOGO-SCHOOL', Storage::disk('public')->get('logos/1.png'));
        $this->assertSame("students/{$a->id}.jpg", $a->fresh()->photo_path);
        $this->assertSame("students/{$b->id}.png", $b->fresh()->photo_path);
    }

    /** @test */
    public function berkas_yang_tidak_ada_dilaporkan_tanpa_menggagalkan_command(): void
    {
        $student = $this->studentWithPhotoRow('0050000003', null);
        $student->update(['photo_path' => "students/{$student->id}.jpg"]);

        $this->artisan('photos:migrate-to-private')
            ->expectsOutputToContain("TIDAK-ADA    student #{$student->id}")
            ->expectsOutputToContain('berkas tidak ada: 1')
            ->assertExitCode(0);

        $this->assertSame("students/{$student->id}.jpg", $student->fresh()->photo_path);
    }

    /** @test */
    public function berkas_yatim_di_public_students_ikut_dipindahkan_bukan_dibiarkan_publik(): void
    {
        Storage::disk('public')->put('students/9999.jpg', 'YATIM');

        $this->artisan('photos:migrate-to-private')
            ->expectsOutputToContain('YATIM        students/9999.jpg')
            ->assertExitCode(0);

        $this->assertSame([], Storage::disk('public')->allFiles('students'));
        $this->assertSame('YATIM', Storage::disk('private')->get('students/9999.jpg'));
    }

    /** @test */
    public function idempoten_dijalankan_dua_kali_tidak_merusak_apa_pun(): void
    {
        $student = $this->studentWithPhotoRow('0050000004', null);
        $path = "students/{$student->id}.jpg";
        $student->update(['photo_path' => $path]);
        Storage::disk('public')->put($path, 'ISI-FOTO');

        $this->artisan('photos:migrate-to-private')->assertExitCode(0);

        $this->artisan('photos:migrate-to-private')
            ->expectsOutputToContain("SUDAH-PRIVAT student #{$student->id}")
            ->expectsOutputToContain('dipindahkan: 0, sudah di private: 1')
            ->assertExitCode(0);

        $this->assertSame('ISI-FOTO', Storage::disk('private')->get($path));
        $this->assertSame([], Storage::disk('public')->allFiles('students'));
        $this->assertSame($path, $student->fresh()->photo_path);
    }

    /** @test */
    public function dry_run_hanya_melaporkan_dan_tidak_memindahkan_apa_pun(): void
    {
        $student = $this->studentWithPhotoRow('0050000005', null);
        $path = "students/{$student->id}.jpg";
        $student->update(['photo_path' => $path]);
        Storage::disk('public')->put($path, 'ISI-FOTO');
        Storage::disk('public')->put('students/9998.jpg', 'YATIM');

        $this->artisan('photos:migrate-to-private --dry-run')
            ->expectsOutputToContain("[dry-run] DIPINDAH     student #{$student->id}")
            ->expectsOutputToContain('[dry-run] YATIM        students/9998.jpg')
            ->assertExitCode(0);

        Storage::disk('public')->assertExists($path);
        Storage::disk('public')->assertExists('students/9998.jpg');
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    /** @test */
    public function bila_salinan_private_sudah_ada_private_dipertahankan_dan_salinan_public_dibuang(): void
    {
        $student = $this->studentWithPhotoRow('0050000006', null);
        $path = "students/{$student->id}.jpg";
        $student->update(['photo_path' => $path]);
        Storage::disk('public')->put($path, 'SALINAN-PUBLIK');
        Storage::disk('private')->put($path, 'SALINAN-PRIVAT');

        $this->artisan('photos:migrate-to-private')
            ->expectsOutputToContain("SUDAH-PRIVAT student #{$student->id}")
            ->assertExitCode(0);

        $this->assertSame('SALINAN-PRIVAT', Storage::disk('private')->get($path));
        Storage::disk('public')->assertMissing($path);
    }
}
