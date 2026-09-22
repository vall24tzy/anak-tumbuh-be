<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DB Design ANAKTUMBUH.ID v1.5 FINAL — Bagian 5.4 (teachers)
// classroom DIHAPUS — scope Teacher sepenuhnya berasal dari teacher_class_assignments
// (prinsip assignment-based, Dokumen v4.0 §9). status untuk Teacher Lifecycle
// (Matrix v5.2 #61, actionable via POST /teachers/{id}/inactive, Headmaster/own school).

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teachers', function (Blueprint $table) {
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
            $table->index('status');
        });

        DB::statement(
            "ALTER TABLE teachers ADD CONSTRAINT chk_teachers_gender ".
            "CHECK (gender IN ('laki-laki','perempuan'))"
        );
        DB::statement(
            "ALTER TABLE teachers ADD CONSTRAINT chk_teachers_status ".
            "CHECK (status IN ('active','inactive'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('teachers');
    }
};
