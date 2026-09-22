<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DB Design ANAKTUMBUH.ID v1.5 FINAL — Bagian 5.11 (indicators)
// Rename dari tabel legacy "indicator" (singular) -> "indicators" (plural, final).
// point NULLABLE (R13): SELALU NULL untuk 4 indikator Bermasyarakat (scoring
// count-based di STUDENTHABIT-001, BUKAN SUM(indicators.point)).
// FIXED reference table — tidak ada CRUD untuk role manapun.

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicators', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('habit_id');
            $table->string('name', 100);
            $table->integer('point')->nullable();
            $table->boolean('is_exclusive')->default(false);
            $table->smallInteger('sort_order');

            $table->foreign('habit_id')->references('id')->on('habits')->cascadeOnDelete();
            $table->unique(['habit_id', 'sort_order']);
        });

        DB::statement(
            "ALTER TABLE indicators ADD CONSTRAINT chk_indicators_point ".
            "CHECK (point IS NULL OR point IN (10,15,25,50))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('indicators');
    }
};
