<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB Design ANAKTUMBUH.ID v1.5 FINAL — Bagian 5.15 (qr_tokens)
// Terikat ke students.id (bukan users.id) — QR adalah kredensial login Student,
// bukan mekanisme generik untuk seluruh User (Dokumen v4.0 §4.1).
// Satu baris per Student (permanent tapi revocable): regenerate meng-UPDATE
// token_hash baris yang sama, otomatis membuat token lama invalid.
// token_hash menyimpan HASH, bukan token mentah — raw token hanya dikirim sekali
// ke Student saat generate/regenerate, tidak pernah disimpan plaintext.

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->unique()->constrained('students')->cascadeOnDelete();
            $table->string('token_hash', 255)->unique();
            $table->timestamp('regenerated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_tokens');
    }
};
