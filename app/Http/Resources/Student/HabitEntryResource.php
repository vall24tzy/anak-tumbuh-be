<?php

namespace App\Http\Resources\Student;

use Illuminate\Http\Resources\Json\JsonResource;

// API Contract v1.2 §21 — response POST /habits/{habit_id}/entries (6 habit
// selain Bermasyarakat). poin_awarded derived (indicator.point + bonus
// initiative), TIDAK dipersist sebagai kolom (Addendum v1.2.1).

class HabitEntryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'habit_id'     => $this->habit_id,
            // GET saja (Keputusan Tim 24 Sep 2026, GAP GET-1): student_id — TIDAK ada di POST.
            ...($request->isMethod('GET') ? ['student_id' => $this->student_id] : []),
            'indicator_id' => $this->indicator_id,
            'initiative'   => $this->initiative,
            'habit_date'   => $this->habit_date->toDateString(),
            'classroom'    => $this->classroom,
            'note'         => $this->note,
            'poin_awarded' => $this->poin_awarded,
        ];
    }
}
