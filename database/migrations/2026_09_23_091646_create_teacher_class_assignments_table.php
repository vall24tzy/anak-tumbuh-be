<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DB Design ANAKTUMBUH.ID v1.5 FINAL — Bagian 5.8 (teacher_class_assignments)

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_class_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('rombel_id')->constrained('rombels')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('rombel_id');
            $table->index('school_id');
        });

        DB::statement(
            "CREATE UNIQUE INDEX uq_tca_one_active_per_teacher_year " .
            "ON teacher_class_assignments (teacher_id, academic_year_id) WHERE is_active = true"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_class_assignments');
    }
};