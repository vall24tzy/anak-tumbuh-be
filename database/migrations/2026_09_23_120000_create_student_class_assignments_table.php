<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DB Design ANAKTUMBUH.ID v1.5 FINAL — Bagian 5.9 (student_class_assignments)
// SATU-SATUNYA source of truth current placement + history Student.
// school_id DENORMALIZED dari rombels.school_id (optimisasi query "own school",
// ditegakkan di application layer saat insert — lihat StudentClassAssignmentService).

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_class_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('rombel_id')->constrained('rombels')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->string('status', 15)->default('active');
            $table->timestamps();

            $table->index('student_id');
            $table->index(['school_id', 'academic_year_id']);
        });

        DB::statement(
            "ALTER TABLE student_class_assignments ADD CONSTRAINT chk_sca_status ".
            "CHECK (status IN ('active','ended'))"
        );

        // Kritikal — menegakkan DUA business rule sekaligus (DB Design v1.5 §5.9):
        // duplicate (same year + same active) DAN conflict (same year + beda kelas),
        // karena index ini tidak peduli rombel_id, hanya peduli
        // (student_id, academic_year_id, status='active').
        DB::statement(
            "CREATE UNIQUE INDEX uq_sca_one_active_per_student_year ".
            "ON student_class_assignments (student_id, academic_year_id) WHERE status = 'active'"
        );

        // Roster kelas saat ini & ranking (partial index, hanya baris aktif).
        DB::statement(
            "CREATE INDEX idx_sca_active_rombel ".
            "ON student_class_assignments (rombel_id) WHERE status = 'active'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('student_class_assignments');
    }
};
