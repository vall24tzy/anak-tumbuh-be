<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// INTEG-PANDUAN-001 — tabel BARU (migration lama tidak diubah). Lihat
// docs/addendum/DB-Design-v1.5-Errata-Habit-Guides.md.
// Satu panduan per (rombel x habit), tanpa tahun ajaran (rombel sudah terikat satu
// tahun ajaran). Teks polos 1-2.000 karakter; teks kosong TIDAK disimpan: kosong/null
// menghapus baris (D-13), jadi CHECK memastikan tidak ada baris kosong.
// rombel_id ON DELETE RESTRICT: rombel yang punya panduan tidak bisa dihapus diam-diam.
// habits.id = smallint (smallIncrements) -> habit_id unsignedSmallInteger, bukan foreignId().

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('habit_guides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rombel_id')->constrained('rombels')->restrictOnDelete();
            $table->unsignedSmallInteger('habit_id');
            $table->text('content');
            $table->timestamps();

            $table->foreign('habit_id')->references('id')->on('habits')->restrictOnDelete();

            // Juga melayani query "semua panduan satu rombel" (rombel_id = kolom pertama).
            $table->unique(['rombel_id', 'habit_id'], 'uq_habit_guides_rombel_habit');
        });

        DB::statement(
            'ALTER TABLE habit_guides ADD CONSTRAINT chk_habit_guides_content '
            . 'CHECK (char_length(content) BETWEEN 1 AND 2000)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('habit_guides');
    }
};
