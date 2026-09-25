<?php

namespace App\Http\Resources\Student;

use Illuminate\Http\Resources\Json\JsonResource;

// API Contract v1.2 §22 — response POST /bermasyarakat/entries. indicator_ids[]
// (native array, derived dari student_habit_indicators). poin_awarded derived
// (formula count-based, DB Design v1.5 §12), TIDAK dipersist.

class BermasyarakatEntryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'habit_id'      => $this->habit_id,
            // GET saja (Keputusan Tim 24 Sep 2026, GAP GET-1): student_id — TIDAK ada di POST.
            ...($request->isMethod('GET') ? ['student_id' => $this->student_id] : []),
            'indicator_ids' => $this->selectedIndicators->pluck('indicator_id')->values(),
            'initiative'    => $this->initiative,
            'habit_date'    => $this->habit_date->toDateString(),
            'classroom'     => $this->classroom,
            'poin_awarded'  => $this->poin_awarded,
        ];
    }
}
