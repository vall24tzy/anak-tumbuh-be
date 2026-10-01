<?php

namespace App\Jobs\Concerns;

use App\Models\ImportBatch;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * F-37 - penanganan kegagalan job import async (Guru dan Siswa).
 *
 * Sebelumnya job tidak punya penanganan kegagalan: bila handle() melempar exception, batch
 * tertahan di 'processing' selamanya dan file upload (berisi PII: NIP/NISN, nama) tidak pernah
 * dihapus, karena hanya jalur sukses yang memanggil Storage::delete().
 *
 * `failed()` dipanggil Laravel saat job gagal PERMANEN: exception di handle() pada percobaan
 * terakhir, melewati batas percobaan (MaxAttemptsExceeded), atau timeout. Karena itu file tidak
 * dihapus pada percobaan yang masih akan di-retry. Tidak ada `$tries`/`backoff` yang diubah:
 * perilaku retry mengikuti konfigurasi worker.
 *
 * Satu rutinitas penutupan (`closeBatchAsFailed`) dipakai bersama jalur abort lifecycle
 * (ActsAsActiveBatchOwner, residual F-14) dan jalur exception ini: status `failed`, failed_rows
 * kosong, file dihapus. Hitungan total/success/failed TIDAK disentuh (lihat di bawah).
 *
 * Pemakai trait harus punya properti `$importBatchId` dan `$storedPath`.
 */
trait ClosesFailedImportBatch
{
    public function failed(?Throwable $exception = null): void
    {
        $batch = ImportBatch::find($this->importBatchId);

        if (!$batch) {
            // Tidak ada batch yang bisa ditandai, tapi file berisi PII tetap harus hilang.
            Storage::delete($this->storedPath);

            return;
        }

        $this->closeBatchAsFailed($batch, $this->storedPath);
    }

    /**
     * Tutup batch sebagai `failed` dan hapus file upload.
     *
     * - Hanya batch yang belum terminal ('pending'/'processing') yang diubah. Batch 'done' tidak
     *   pernah diturunkan jadi 'failed' (mis. bila ada exception SETELAH hasil dicatat).
     * - total/success/failed sengaja tidak diubah: nilai itu baru ditulis di akhir job yang
     *   sukses, jadi pada kegagalan tetap nilai awal. Baris yang sempat dibuat sebelum gagal
     *   (tiap baris punya transaksi sendiri di service) tidak di-rollback dan tidak dihitung.
     */
    protected function closeBatchAsFailed(ImportBatch $batch, string $storedPath): void
    {
        if (in_array($batch->status, ['pending', 'processing'], true)) {
            $batch->update(['status' => 'failed', 'failed_rows' => []]);
        }

        Storage::delete($storedPath);
    }
}
