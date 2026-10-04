<?php

namespace App\Services\User;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

// Infrastructure OPSIONAL (DB Design v1.5 §5.16, owner AUTH-003). Method ini
// TIDAK BOLEH PERNAH melempar exception ke pemanggil — reset password/QR
// regenerate harus tetap sukses walau logging gagal atau tabelnya belum ada.
class AuditLogService
{
    // F-31 (Matrix v3.1, keputusan FINAL): audit_logs.target_user_id nullable (DB Design §5.16:
    // audit event tidak selalu punya target user). $target opsional; semua pemanggil saat ini
    // tetap mengirim target, jadi perilakunya tidak berubah.
    public function record(User $actor, string $action, ?User $target = null): void
    {
        if (!Schema::hasTable('audit_logs')) {
            return; // infrastructure belum diaktifkan — bukan error
        }

        try {
            AuditLog::create([
                'actor_user_id'  => $actor->id,
                'action'         => $action,
                'target_user_id' => $target?->id,
            ]);
        } catch (Throwable $e) {
            Log::warning('audit_logs write failed (non-blocking)', ['error' => $e->getMessage()]);
        }
    }
}