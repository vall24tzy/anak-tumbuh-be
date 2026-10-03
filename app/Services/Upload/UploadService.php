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
     * Upload & resize gambar, simpan dengan nama {identifier}.{ext} di disk $disk
     * ('public' untuk logo School; 'private' untuk foto Student, F-06).
     *
     * Replacement (F-08): $oldPath dihapus HANYA setelah file baru benar-benar tersimpan.
     * Urutan: decode -> resize -> encode -> simpan -> hapus file lama. Bila langkah mana pun
     * sebelum "hapus file lama" gagal (file rusak, format tidak didukung, penyimpanan gagal),
     * file lama dan path lama di database tetap valid.
     */
    public function upload(
        UploadedFile $file,
        string $folder,
        int|string $identifier,
        ?string $oldPath = null,
        string $disk = 'public'
    ): string {
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

        // Disk `public` dikonfigurasi `throw => false`: put() mengembalikan false (tanpa exception)
        // saat gagal. Nilai itu harus diperiksa; kalau tidak, path ke file yang tidak ada akan
        // dikembalikan dan file lama sudah (atau akan) hilang.
        if (Storage::disk($disk)->put($relativePath, (string) $encoded) === false) {
            throw new \RuntimeException("Gagal menyimpan file upload: {$relativePath}");
        }

        // Nama file deterministik ({folder}/{identifier}.{ext}): bila ekstensi lama dan baru sama,
        // path-nya identik dan put() di atas sudah menimpa file lama di tempat. Menghapus
        // $oldPath pada kasus itu justru menghapus file yang baru disimpan.
        if ($oldPath && $oldPath !== $relativePath) {
            $this->delete($oldPath, $disk);
        }

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

    public function delete(?string $path, string $disk = 'public'): void
    {
        if ($path && Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }
}