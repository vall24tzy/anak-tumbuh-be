<?php

namespace Tests\Concerns;

use Illuminate\Http\UploadedFile;

// Pembuat gambar nyata untuk test upload (Stage 2B: F-08, F-07, F-06). Memakai file temp
// sungguhan (Illuminate\Http\UploadedFile), bukan UploadedFile::fake(), supaya mime dideteksi
// dari KONTEN seperti di produksi. Butuh ekstensi PHP gd. Panggil cleanupImageFiles() di tearDown.

trait MakesImageUploads
{
    /** @var string[] */
    private array $imageTempFiles = [];

    protected function cleanupImageFiles(): void
    {
        foreach ($this->imageTempFiles as $file) {
            @unlink($file);
        }

        $this->imageTempFiles = [];
    }

    /** Bytes gambar asli; $noise=true membuat piksel acak supaya berkas cukup besar untuk dipotong. */
    protected function imageBytes(string $format = 'jpeg', int $width = 40, int $height = 40, bool $noise = false): string
    {
        $image = imagecreatetruecolor($width, $height);

        if ($noise) {
            for ($x = 0; $x < $width; $x++) {
                for ($y = 0; $y < $height; $y++) {
                    imagesetpixel($image, $x, $y, imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
                }
            }
        }

        ob_start();
        match ($format) {
            'jpeg' => imagejpeg($image),
            'png'  => imagepng($image),
            'webp' => imagewebp($image),
        };
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    protected function uploadFromBytes(string $bytes, string $clientName): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'img_');
        file_put_contents($path, $bytes);
        $this->imageTempFiles[] = $path;

        return new UploadedFile($path, $clientName, null, null, true);
    }

    protected function imageUpload(string $clientName, string $format = 'jpeg', int $width = 40, int $height = 40): UploadedFile
    {
        return $this->uploadFromBytes($this->imageBytes($format, $width, $height), $clientName);
    }

    /** PNG berkas asli tetapi dipotong: header utuh (lolos image/mimes/dimensions), data rusak (gagal decode). */
    protected function truncatedPngUpload(string $clientName = 'rusak.png'): UploadedFile
    {
        $bytes = $this->imageBytes('png', 120, 120, true);

        return $this->uploadFromBytes(substr($bytes, 0, (int) (strlen($bytes) * 0.5)), $clientName);
    }

    /** PNG palet satu warna: ukuran berkas kecil walau dimensinya besar (tanpa lonjakan memori). */
    protected function paletteImageUpload(int $width, int $height, string $clientName = 'besar.png'): UploadedFile
    {
        $image = imagecreate($width, $height);
        imagecolorallocate($image, 10, 120, 200);

        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $this->uploadFromBytes($bytes, $clientName);
    }
}
