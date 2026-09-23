<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DB Design ANAKTUMBUH.ID v1.5 FINAL — Bagian 5.7 (rombels)

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rombels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->string('name', 30);
            $table->integer('grade_level');
            $table->timestamps();

            $table->index(['school_id', 'academic_year_id']);
            $table->index(['school_id', 'grade_level']);
        });

        DB::statement(
            "ALTER TABLE rombels ADD CONSTRAINT chk_rombels_grade_level " .
            "CHECK (grade_level > 0)"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('rombels');
    }
};