<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_habits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('habit_id')->constrained('habits')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('indicator_id')->constrained('indicator')->cascadeOnDelete();
            // Diisi OTOMATIS dari students.classroom saat entri dibuat (snapshot untuk histori kenaikan kelas).
            // Logic pengisiannya dikerjakan di task berikutnya (BACKEND-003), di sini cukup kolomnya.
            $table->string('classroom')->nullable();
            $table->longText('description')->nullable();
            $table->longText('note')->nullable();
            $table->string('inisiative')->nullable(); // 'Mandiri' | 'Disuruh'
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_habits');
    }
};
