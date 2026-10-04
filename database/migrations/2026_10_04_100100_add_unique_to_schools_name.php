<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// F-11 (Matrix v3.1, keputusan FINAL): schools.name UNIQUE di aplikasi DAN constraint database,
// supaya request konkuren tidak bisa menghasilkan sekolah dengan nama kembar. schools.id tetap
// primary identifier. DB Design v1.5 §5.2 perlu diselaraskan (Matrix §9).
//
// Constraint gagal dibuat bila data existing sudah kembar. Cek dulu dan berhenti dengan pesan
// jelas. Query yang sama bisa dijalankan manual sebelum migrate:
//   SELECT name, COUNT(*), array_agg(id) FROM schools GROUP BY name HAVING COUNT(*) > 1;
// Keunikan biasa (case-sensitive), sama dengan `unique:schools,name` di request.

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('schools')
            ->select('name', DB::raw('COUNT(*) AS total'))
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            $list = $duplicates->map(fn ($r) => "\"{$r->name}\" ({$r->total}x)")->implode('; ');

            throw new RuntimeException(
                'F-11: tidak bisa menambah UNIQUE schools.name, ada nama sekolah kembar: ' . $list
                . '. Ganti nama salah satunya (atau gabungkan data) lalu jalankan migrate ulang.'
            );
        }

        Schema::table('schools', function (Blueprint $table) {
            $table->unique('name'); // nama constraint Laravel: schools_name_unique
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropUnique('schools_name_unique');
        });
    }
};
