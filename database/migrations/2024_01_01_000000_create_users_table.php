<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DB Design ANAKTUMBUH.ID v1.5 FINAL — Bagian 5.1 (users)
// Fresh Deployment (§10.A) — kolom final langsung dibuat sejak awal, tanpa ALTER menyusul.

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('role', 20);
            $table->string('username', 100)->unique();
            $table->string('password', 255);
            $table->timestamps();

            $table->index('role');
        });

        DB::statement(
            "ALTER TABLE users ADD CONSTRAINT chk_users_role ".
            "CHECK (role IN ('super_admin','headmaster','teacher','student'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
