<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DB Design ANAKTUMBUH.ID v1.5 FINAL — Bagian 5.12 (student_habits)
// [REWORK STUDENTHABIT-001] Jalur Fresh Deployment: bentuk final langsung
// dibuat di sini, TANPA ALTER menyusul. TIDAK ADA description/poin snapshot/
// is_mandiri (kolom lama) — classroom & initiative WAJIB ada sejak awal.
// indicator_id NULLABLE: NULL untuk Bermasyarakat (pilihannya multi-select
// di student_habit_indicators, migration terpisah).
// classroom = historical snapshot (diisi SYSTEM saat INSERT dari
// student_class_assignments aktif, TIDAK PERNAH di-UPDATE) — BUKAN source
// of truth placement (Risk #1 tetap berlaku, students.classroom tetap
// tidak ada).

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_habits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();

            // habits.id = smallIncrements (smallint) & indicators.id = increments
            // (int) — BUKAN bigint. foreignId() default-nya bigint dan akan
            // menyebabkan FK type mismatch di Postgres saat migrate:fresh.
            // Ikuti pola yang sudah dipakai indicators.habit_id (HABIT-001).
            $table->unsignedSmallInteger('habit_id');
            $table->unsignedInteger('indicator_id')->nullable();

            $table->date('habit_date');
            $table->string('note', 1000)->nullable();
            $table->string('classroom', 30)->nullable();
            $table->string('initiative', 10);
            $table->timestamps();

            $table->foreign('habit_id')->references('id')->on('habits');
            $table->foreign('indicator_id')->references('id')->on('indicators');

            $table->unique(['student_id', 'habit_id', 'habit_date'], 'uq_student_habit_date');
            $table->index(['student_id', 'habit_date']);
            $table->index(['habit_id', 'habit_date']);
        });

        // initiative: 'mandiri' (+10 poin) / 'disuruh' (+0) — wajib SEMUA 7 habit
        // (API Contract v1.2 Addendum v1.2.1, DB Design v1.5 R17).
        DB::statement(
            "ALTER TABLE student_habits ADD CONSTRAINT chk_student_habits_initiative ".
            "CHECK (initiative IN ('mandiri','disuruh'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('student_habits');
    }
};
