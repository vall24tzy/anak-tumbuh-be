<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DB Design ANAKTUMBUH.ID v1.5 FINAL — Bagian 5.3 (headmasters)
// Nama tabel PLURAL "headmasters" — koreksi dari skema legacy yang singular "headmaster"
// (tidak ada instruksi baseline manapun yang mewajibkan nama tabel singular).
// status + partial unique index menegakkan "1 Headmaster active per School" (R1/R2).
// Suksesi Headmaster (nonaktifkan lama sebelum aktifkan baru) adalah efek samping
// SYSTEM pada POST /headmasters — ditangani di application layer (HEADMASTER-001),
// bukan di migration ini.

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('headmasters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('fullname', 150);
            $table->string('nip', 30)->unique();
            $table->string('gender', 15);
            $table->string('photo_path', 255)->nullable();
            $table->string('status', 15)->default('active');
            $table->timestamps();

            $table->index('school_id');
        });

        DB::statement(
            "ALTER TABLE headmasters ADD CONSTRAINT chk_headmasters_gender ".
            "CHECK (gender IN ('laki-laki','perempuan'))"
        );
        DB::statement(
            "ALTER TABLE headmasters ADD CONSTRAINT chk_headmasters_status ".
            "CHECK (status IN ('active','inactive'))"
        );
        DB::statement(
            "CREATE UNIQUE INDEX uq_headmasters_one_active_per_school ".
            "ON headmasters (school_id) WHERE status = 'active'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('headmasters');
    }
};
