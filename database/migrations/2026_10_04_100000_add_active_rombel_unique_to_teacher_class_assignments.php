<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// F-18 (Matrix v3.1, keputusan FINAL): dalam satu Rombel pada satu Academic Year maksimal SATU
// Teacher assignment aktif (Teacher itu = wali kelas sistem). Invarian dijaga di DB supaya
// request konkuren tidak bisa menghasilkan dua Teacher aktif. Melengkapi
// uq_tca_one_active_per_teacher_year (DB Design v1.5 §5.8); §5.8 perlu diselaraskan (Matrix §9).
//
// Index gagal dibuat bila data existing sudah melanggar. Cek dulu dan berhenti dengan pesan
// jelas, jangan biarkan CREATE UNIQUE INDEX gagal dengan error generik. Query yang sama bisa
// dijalankan manual sebelum migrate:
//   SELECT rombel_id, academic_year_id, COUNT(*) FROM teacher_class_assignments
//   WHERE is_active = true GROUP BY rombel_id, academic_year_id HAVING COUNT(*) > 1;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('teacher_class_assignments')
            ->select('rombel_id', 'academic_year_id', DB::raw('COUNT(*) AS total'))
            ->where('is_active', true)
            ->groupBy('rombel_id', 'academic_year_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            $list = $duplicates
                ->map(fn ($r) => "rombel_id={$r->rombel_id}, academic_year_id={$r->academic_year_id} ({$r->total} assignment aktif)")
                ->implode('; ');

            throw new RuntimeException(
                'F-18: tidak bisa membuat uq_tca_one_active_per_rombel_year, ada Rombel dengan >1 Teacher aktif pada '
                . 'Academic Year yang sama: ' . $list . '. Nonaktifkan duplikatnya lewat '
                . 'POST /teacher-class-assignments/{id}/deactivate (atau perbaiki data) lalu jalankan migrate ulang.'
            );
        }

        DB::statement(
            "CREATE UNIQUE INDEX uq_tca_one_active_per_rombel_year " .
            "ON teacher_class_assignments (rombel_id, academic_year_id) WHERE is_active = true"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_tca_one_active_per_rombel_year');
    }
};
