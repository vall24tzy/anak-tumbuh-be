<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DB Design ANAKTUMBUH.ID v1.5 FINAL — Bagian 5.5 (students)
// classroom & grade_level SENGAJA TIDAK DIBUAT (Risk #1) — digantikan sepenuhnya
// oleh student_class_assignments + rombels.grade_level (prinsip assignment-based,
// Dokumen v4.0 §9), supaya tidak ada dua source of truth.
// level/gamification_level juga tidak disimpan — dihitung real-time dari poin
// terhadap gamification_levels (LEVEL-RANKING-001).
// Fresh Deployment (§10.A): kolom final (termasuk status) langsung dibuat di sini,
// tanpa migration ALTER terpisah menyusul.

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('fullname', 150);
            $table->string('nisn', 20)->unique();
            $table->string('gender', 15);
            $table->string('photo_path', 255)->nullable();
            $table->integer('poin')->default(0);
            $table->string('status', 15)->default('active');
            $table->timestamps();

            $table->index('school_id');
            $table->index('status');
            $table->index(['school_id', 'status']);
        });

        DB::statement(
            "ALTER TABLE students ADD CONSTRAINT chk_students_gender ".
            "CHECK (gender IN ('laki-laki','perempuan'))"
        );
        DB::statement(
            "ALTER TABLE students ADD CONSTRAINT chk_students_status ".
            "CHECK (status IN ('active','inactive','graduated'))"
        );
        DB::statement(
            "ALTER TABLE students ADD CONSTRAINT chk_students_poin ".
            "CHECK (poin >= 0)"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
