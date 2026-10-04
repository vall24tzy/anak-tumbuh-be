<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// F-31 (Matrix v3.1, keputusan FINAL): DB Design v1.5 §5.16 = acuan. audit_logs.target_user_id
// nullable karena audit event tidak selalu punya target user. Migration ini hanya melonggarkan
// constraint (data existing tidak berubah). audit_logs infrastruktur OPSIONAL (DB Design §5.16),
// jadi dilewati bila tabelnya tidak ada.

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('audit_logs')) {
            return;
        }

        DB::statement('ALTER TABLE audit_logs ALTER COLUMN target_user_id DROP NOT NULL');
    }

    public function down(): void
    {
        if (!Schema::hasTable('audit_logs')) {
            return;
        }

        // Tidak menghapus log audit demi memulihkan NOT NULL: bila sudah ada baris tanpa target,
        // rollback berhenti dengan pesan jelas.
        if (DB::table('audit_logs')->whereNull('target_user_id')->exists()) {
            throw new RuntimeException('F-31: tidak bisa mengembalikan NOT NULL, ada audit_logs tanpa target_user_id.');
        }

        DB::statement('ALTER TABLE audit_logs ALTER COLUMN target_user_id SET NOT NULL');
    }
};
