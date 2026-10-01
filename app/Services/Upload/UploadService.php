<?php

namespace App\Services\Upload;

use App\Exceptions\ApiException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class UploadService
{
    protected int $maxDimension = 500;

    // F-05: ekstensi file di disk hanya boleh berasal dari format yang BENAR-BENAR
    // dihasilkan encoder (diturunkan dari konten gambar), bukan dari nama file klien.
    // Allowlist: jpg / png / webp.
    protected array $extensionByMediaType = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Upload & resize gambar, simpan dengan nama {identifier}.{ext}.
     * Kalau $oldPath diisi, file lama dihapus dulu sebelum yang baru disimpan (replacement).
     */
    public function upload(UploadedFile $file, string $folder, int|string $identifier, ?string $oldPath = null): string
    {
        if ($oldPath) {
            $this->delete($oldPath);
        }

        $manager = ImageManager::usingDriver(Driver::class);

        // decodeSplFileInfo() dipakai khusus untuk baca UploadedFile Laravel
        // langsung, tanpa perlu getRealPath() manual.
        $image = $manager->decodeSplFileInfo($file);

        // scaleDown: perbesar tidak dilakukan (no upscale), hanya diperkecil
        // kalau melebihi 500x500, rasio aspek tetap terjaga (tidak di-crop paksa).
        $image->scaleDown($this->maxDimension, $this->maxDimension);

        // encode() tanpa argumen memakai format asal gambar (hasil deteksi konten).
        // Ekstensi diambil dari media type hasil encoder; nama klien diabaikan.
        $encoded = $image->encode();
        $extension = $this->extensionFor($encoded->mediaType());
        $relativePath = "{$folder}/{$identifier}.{$extension}";

        Storage::disk('public')->put($relativePath, (string) $encoded);

        return $relativePath;
    }

    // Fail-closed: format di luar allowlist tidak pernah disimpan. Di jalur HTTP
    // cabang ini tidak tercapai karena rule `mimes:jpg,jpeg,png,webp` menolak lebih
    // dulu; ini pertahanan berlapis bila service dipanggil dari jalur lain.
    protected function extensionFor(string $mediaType): string
    {
        $extension = $this->extensionByMediaType[strtolower($mediaType)] ?? null;

        if ($extension === null) {
            throw new ApiException('validation_failed', 422, 'Format gambar tidak didukung (hanya jpg, png, webp)');
        }

        return $extension;
    }

    public function delete(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}