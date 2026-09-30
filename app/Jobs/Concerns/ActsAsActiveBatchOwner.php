<?php

namespace App\Jobs\Concerns;

use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Stage 0-B residual F-14 - gerbang lifecycle untuk job import di queue worker.
 *
 * Job tidak membawa JWT dari request awal, jadi middleware `jwt`
 * (AuthenticateActiveSession) tidak pernah dijalankan. Sebelumnya job langsung
 * memanggil auth('api')->login($actingUser) sehingga Headmaster/Teacher yang
 * sudah inactive tetap memperoleh authority aktif selama file masih antre.
 *
 * Aturan (sama dengan gate login & middleware):
 *  - pemilik batch harus ada dan User::isLifecycleActive() true;
 *  - selain itu FAIL-CLOSED: tidak login, tidak ada service yang dijalankan,
 *    batch jadi terminal ('failed'), file upload (mengandung PII) dihapus.
 *
 * Pemilik batch yang hilang (created_by NULL karena user dihapus) juga
 * fail-closed. Sebelumnya job lanjut tanpa login, dan di worker yang hidup
 * lama guard bisa masih memegang user dari job sebelumnya.
 */
trait ActsAsActiveBatchOwner
{
    /**
     * @return bool true = pemilik aktif dan sudah di-login; false = job harus berhenti.
     */
    protected function actAsActiveBatchOwner(ImportBatch $batch, string $storedPath): bool
    {
        $actingUser = $batch->created_by ? User::find($batch->created_by) : null;

        if (!$actingUser || !$actingUser->isLifecycleActive()) {
            $batch->update(['status' => 'failed', 'failed_rows' => []]);
            Storage::delete($storedPath);

            return false;
        }

        auth('api')->login($actingUser);

        return true;
    }
}
