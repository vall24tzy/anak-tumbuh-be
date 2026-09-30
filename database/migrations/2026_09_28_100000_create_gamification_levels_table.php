<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DB Design ANAKTUMBUH.ID v1.5 FINAL — Bagian 5.14 (gamification_levels)
// FIXED reference table — tidak ada CRUD untuk role manapun (API Contract v1.2 §23).
// Level TIDAK disimpan sebagai kolom di students — dihitung real-time dari
// students.poin terhadap tabel ini (LevelService), supaya tidak ada dua source of truth.

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gamification_levels', function (Blueprint $table) {
            $table->smallInteger('level')->primary();
            $table->integer('required_poin');

            $table->index('required_poin');
        });

        DB::statement(
            "ALTER TABLE gamification_levels ADD CONSTRAINT chk_gamification_levels_required_poin ".
            "CHECK (required_poin >= 0)"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('gamification_levels');
    }
};
