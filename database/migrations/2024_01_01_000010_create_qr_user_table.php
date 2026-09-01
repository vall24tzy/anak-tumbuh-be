<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('username');
            $table->string('password');
            // Menyimpan URL LENGKAP berisi username & password sebagai query param
            // (cth: https://anaktumbuh.id?username=xxx&password=xxx) sesuai ERD terbaru.
            // CATATAN KEAMANAN: supaya link ini bisa dipakai auto-login, password
            // kemungkinan perlu disimpan reversible (tidak di-hash) — tandai ulang
            // ke tim sebelum logic QR login ditulis di BACKEND-003.
            $table->text('link_qr');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_user');
    }
};
