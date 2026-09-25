<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB Design ANAKTUMBUH.ID v1.5 FINAL — Bagian 5.13 (student_habit_indicators)
// Pivot BARU, khusus Bermasyarakat (habits.allows_multi_select=true). Satu
// baris student_habits (Bermasyarakat, per hari, indicator_id induk NULL)
// menaungi banyak baris di sini — satu baris per indikator yang dipilih.
// Mutual exclusivity "Kurang bermasyarakat" adalah application-layer
// validation (STUDENTHABIT-001 Service), bukan constraint DB.

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_habit_indicators', function (Blueprint $table) {
            $table->id();
            // student_habits.id = bigIncrements -> foreignId() (bigint) cocok.
            $table->foreignId('student_habit_id')->constrained('student_habits')->cascadeOnDelete();

            // indicators.id = increments (int biasa, BUKAN bigint) — sama
            // seperti catatan di migration student_habits, foreignId() akan
            // mismatch. Ikuti pola indicators.habit_id (HABIT-001).
            $table->unsignedInteger('indicator_id');
            $table->foreign('indicator_id')->references('id')->on('indicators');

            $table->unique(['student_habit_id', 'indicator_id']);
            $table->index('student_habit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_habit_indicators');
    }
};
