<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// D-19 (pemilik, 22 Okt 2026) — habit_guides.rombel_id: ON DELETE RESTRICT -> CASCADE.
// Migration BARU: 2026_10_20_100000_create_habit_guides_table.php tidak diedit karena sudah
// ter-apply di database tim; database yang sudah menjalankannya wajib `php artisan migrate`.
//
// Menghapus rombel ikut menghapus panduan kebiasaan rombel itu (teks pendamping saja).
// Yang TIDAK berubah: rombel yang masih direferensikan assignment guru/siswa (aktif maupun
// riwayat) tetap ditolak oleh FK RESTRICT milik tabel assignment (409 rombel_has_active_assignment),
// dan penolakan itu terjadi dalam satu statement sehingga panduan tidak ikut terhapus.
// habit_guides.habit_id tetap RESTRICT.
// Nama constraint bawaan Laravel: habit_guides_rombel_id_foreign (diperiksa di database).

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('habit_guides', function (Blueprint $table) {
            $table->dropForeign(['rombel_id']);
            $table->foreign('rombel_id')->references('id')->on('rombels')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('habit_guides', function (Blueprint $table) {
            $table->dropForeign(['rombel_id']);
            $table->foreign('rombel_id')->references('id')->on('rombels')->restrictOnDelete();
        });
    }
};
