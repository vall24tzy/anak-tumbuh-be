<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// CATATAN: Laravel 11 default sudah punya migration bawaan
// "0001_01_01_000000_create_users_table.php" (isinya users + password_reset_tokens + sessions).
// GANTI file bawaan itu dengan file ini (dan file password_reset_tokens terpisah di bawah),
// atau hapus definisi tabel users bawaan supaya tidak bentrok "table already exists".
// Tabel "sessions" bawaan Laravel boleh dibiarkan tetap ada (tidak dipakai bisnis logic ANAKTUMBUH,
// tapi tidak konflik juga kalau mau tetap disimpan untuk kebutuhan framework).

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('username');
            $table->string('email')->unique();
            $table->string('password');
            $table->integer('level');
            $table->integer('is_active')->default(1);
            $table->text('access_token')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
