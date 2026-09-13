<?php

namespace App\Services\Upload;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class UploadService
{
    protected int $maxDimension = 500;

    /**
     * Upload & resize gambar, simpan dengan nama {identifier}.{ext}.
     * Kalau $oldPath diisi, file lama dihapus dulu sebelum yang baru disimpan (replacement).
     */
    public function upload(UploadedFile $file, string $folder, int|string $identifier, ?string $oldPath = null): string
    {
        if ($oldPath) {
            $this->delete($oldPath);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $relativePath = "{$folder}/{$identifier}.{$extension}";

        $manager = ImageManager::usingDriver(Driver::class);

        // decodeSplFileInfo() dipakai khusus untuk baca UploadedFile Laravel
        // langsung, tanpa perlu getRealPath() manual.
        $image = $manager->decodeSplFileInfo($file);

        // scaleDown: perbesar tidak dilakukan (no upscale), hanya diperkecil
        // kalau melebihi 500x500, rasio aspek tetap terjaga (tidak di-crop paksa).
        $image->scaleDown($this->maxDimension, $this->maxDimension);

        Storage::disk('public')->put($relativePath, (string) $image->encode());

        return $relativePath;
    }

    public function delete(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}