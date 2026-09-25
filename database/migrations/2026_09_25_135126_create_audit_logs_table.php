<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB Design v1.5 §5.16 — opsional/technical infrastructure recommendation,
// BUKAN mandatory schema (owner: AUTH-003). Reset password & QR regenerate
// TETAP harus berhasil walau tabel ini tidak ada — lihat AuditLogService.

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->constrained('users');
            $table->string('action', 50);
            $table->foreignId('target_user_id')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index('actor_user_id');
            $table->index('target_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};