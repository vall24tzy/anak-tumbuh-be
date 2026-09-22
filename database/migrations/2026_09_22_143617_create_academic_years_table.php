<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DB Design ANAKTUMBUH.ID v1.5 FINAL — Bagian 5.6 (academic_years)
// Partial unique index (WHERE is_active=true) menegakkan "1 Academic Year
// active per school" — bukan UNIQUE biasa, karena 1 school boleh punya
// banyak Academic Year historis, cuma 1 yang boleh aktif.

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('name', 30);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->index('school_id');
        });

        DB::statement(
            "ALTER TABLE academic_years ADD CONSTRAINT chk_academic_years_period " .
            "CHECK (end_date > start_date)"
        );
        DB::statement(
            "CREATE UNIQUE INDEX uq_academic_years_one_active_per_school " .
            "ON academic_years (school_id) WHERE is_active = true"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_years');
    }
};