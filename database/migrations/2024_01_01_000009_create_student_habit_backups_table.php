<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hanya 3 kolom sesuai ERD final, tanpa timestamps.
        // Logic pasti kapan/bagaimana tabel ini dipakai saat kenaikan kelas
        // masih perlu dirancang di task berikutnya (BACKEND-003) — lihat sheet
        // "Draft Skema - Perlu Konfirmasi" di task board untuk catatannya.
        Schema::create('student_habit_backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('student_habit_id')->constrained('student_habits')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_habit_backups');
    }
};
