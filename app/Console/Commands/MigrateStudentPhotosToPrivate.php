<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Services\Student\StudentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

// F-06 (data migration, BUKAN schema migration): pindahkan foto Student yang terlanjur tersimpan
// di disk publik (storage/app/public/students/) ke disk privat, lalu hapus salinan publiknya.
//
// Skema path: TIDAK berubah. photo_path tetap `students/{id}.{ext}` (relatif); hanya disk yang
// berbeda (public -> private, root storage/app/private). Karena itu kolom photo_path di
// database tidak diubah oleh command ini.
//
// Cara kerja:
//   1. Setiap baris `students` dengan photo_path tidak null dilaporkan satu per satu:
//        DIPINDAH   berkas ada di public, disalin ke private (ukuran diverifikasi), salinan public dihapus
//        SUDAH-PRIVAT  berkas sudah ada di private; salinan public (bila ada) dibuang
//        TIDAK-ADA  berkas tidak ditemukan di public maupun private (photo_path menggantung)
//        GAGAL      salinan gagal atau ukuran tidak cocok; salinan public DIPERTAHANKAN
//   2. Berkas sisa di public/students/ yang tidak dirujuk baris mana pun (yatim) tetap bisa diakses
//      publik, jadi ikut dipindahkan ke private (tidak dihapus) dan dilaporkan sebagai YATIM.
//   3. Idempoten: dijalankan ulang tidak merusak apa pun; yang sudah di private hanya dilewati.
//   4. Logo School (folder logos/) tidak disentuh.
//
//   php artisan photos:migrate-to-private --dry-run   (hanya melaporkan)
//   php artisan photos:migrate-to-private

class MigrateStudentPhotosToPrivate extends Command
{
    protected $signature = 'photos:migrate-to-private {--dry-run : Hanya laporkan, tidak memindahkan atau menghapus}';

    protected $description = 'Pindahkan foto Student dari disk publik ke disk privat dan hapus salinan publiknya (F-06)';

    public function handle(): int
    {
        $public = Storage::disk('public');
        $private = Storage::disk(StudentService::PHOTO_DISK);
        $dryRun = (bool) $this->option('dry-run');
        $prefix = $dryRun ? '[dry-run] ' : '';

        $counts = ['moved' => 0, 'already' => 0, 'missing' => 0, 'failed' => 0, 'orphan' => 0];
        $referenced = [];

        Student::whereNotNull('photo_path')->orderBy('id')->each(function (Student $student) use ($public, $private, $dryRun, $prefix, &$counts, &$referenced) {
            $path = $student->photo_path;
            $referenced[$path] = true;
            $label = "student #{$student->id} {$path}";

            if ($private->exists($path)) {
                if (!$dryRun && $public->exists($path)) {
                    $public->delete($path);
                }
                $this->line("{$prefix}SUDAH-PRIVAT {$label}");
                $counts['already']++;

                return;
            }

            if (!$public->exists($path)) {
                $this->warn("{$prefix}TIDAK-ADA    {$label}: berkas tidak ditemukan di public maupun private");
                $counts['missing']++;

                return;
            }

            if ($dryRun) {
                $this->line("{$prefix}DIPINDAH     {$label}");
                $counts['moved']++;

                return;
            }

            if ($this->relocate($path)) {
                $this->line("DIPINDAH     {$label}");
                $counts['moved']++;
            } else {
                $this->error("GAGAL        {$label}: salinan public dipertahankan");
                $counts['failed']++;
            }
        });

        foreach ($public->allFiles('students') as $path) {
            if (isset($referenced[$path])) {
                continue;
            }

            if ($dryRun) {
                $this->line("{$prefix}YATIM        {$path}");
                $counts['orphan']++;

                continue;
            }

            if ($private->exists($path) ? $public->delete($path) : $this->relocate($path)) {
                $this->line("YATIM        {$path}: dipindahkan ke private");
                $counts['orphan']++;
            } else {
                $this->error("GAGAL        {$path} (yatim): salinan public dipertahankan");
                $counts['failed']++;
            }
        }

        $this->info(
            "{$prefix}dipindahkan: {$counts['moved']}, sudah di private: {$counts['already']}, "
            . "yatim dipindahkan: {$counts['orphan']}, berkas tidak ada: {$counts['missing']}, gagal: {$counts['failed']}"
        );

        return $counts['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** Salin public -> private, verifikasi ukuran, baru hapus salinan public. */
    protected function relocate(string $path): bool
    {
        $public = Storage::disk('public');
        $private = Storage::disk(StudentService::PHOTO_DISK);

        $stream = $public->readStream($path);

        if (!is_resource($stream)) {
            return false;
        }

        $written = $private->writeStream($path, $stream);
        fclose($stream);

        if ($written === false || $private->size($path) !== $public->size($path)) {
            $private->delete($path);

            return false;
        }

        return (bool) $public->delete($path);
    }
}
