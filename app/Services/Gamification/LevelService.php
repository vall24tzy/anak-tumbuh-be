<?php

namespace App\Services\Gamification;

use App\Models\GamificationLevel;

// LEVEL-RANKING-001 — API Contract v1.2 §23, DB Design v1.5 §5.5/§5.14.
// Level dihitung REAL-TIME dari students.poin terhadap gamification_levels
// (level tertinggi yang required_poin-nya <= poin), TIDAK disimpan sebagai
// kolom. Level tidak pernah di-reset — hanya bergantung total poin.
//
// Service ini reusable: DashboardService (Student) mengintegrasikannya di
// MERGE-001, bukan di task ini.

class LevelService
{
    public function levelForPoin(int $poin): int
    {
        // students.poin CHECK >= 0; max() sekadar pengaman.
        $level = GamificationLevel::where('required_poin', '<=', max($poin, 0))->max('level');

        // Default 0 (Dokumen v4.0 §9: gamification_level default = 0).
        return $level === null ? 0 : (int) $level;
    }

    /**
     * Batch — dipakai RankingService (LEVEL-RANKING-001 revisi 3, poin A.2):
     * baca threshold SEKALI (1 query gamification_levels, bukan per baris),
     * hitung di memori. Tidak mengubah perilaku levelForPoin().
     *
     * @param  array<int|string, int>  $poins  key dipertahankan (mis. index koleksi).
     * @return array<int|string, int>
     */
    public function levelsForPoins(array $poins): array
    {
        // [required_poin => level], terurut ASC (kunci Collection::pluck).
        $thresholds = GamificationLevel::orderBy('required_poin')->pluck('level', 'required_poin');

        $requiredPoints = $thresholds->keys()->all();
        $levelsAsc = $thresholds->values()->all();

        $result = [];

        foreach ($poins as $key => $poin) {
            $poin = max((int) $poin, 0);
            $level = 0;

            foreach ($requiredPoints as $i => $rp) {
                if ($rp > $poin) {
                    break;
                }
                $level = $levelsAsc[$i];
            }

            $result[$key] = $level;
        }

        return $result;
    }
}
