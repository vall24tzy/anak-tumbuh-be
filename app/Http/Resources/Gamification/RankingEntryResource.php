<?php

namespace App\Http\Resources\Gamification;

use Illuminate\Http\Resources\Json\JsonResource;

// KEPUTUSAN TIM revisi 3 (docs/db-gap-analysis.md NOTE-11 & addendum
// Level-Ranking poin 2 & 6): student_id, name, rombel, poin, level — TANPA
// rank/nisn/nested Student. name = pengecualian sadar dari "no classmates
// list" (Dokumen v4.0 §9). Field "level" (bukan "gamification_level" §23).
class RankingEntryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'student_id' => (int) $this->student_id,
            'name'       => $this->name,
            'rombel'     => $this->rombel,
            'poin'       => (int) $this->poin,
            'level'      => (int) $this->level,
        ];
    }
}
