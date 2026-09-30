<?php

namespace App\Http\Resources\Gamification;

use Illuminate\Http\Resources\Json\JsonResource;

// API Contract v1.2 §23: { student_id, poin, gamification_level }.
class GamificationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'student_id'         => $this->resource['student_id'],
            'poin'               => $this->resource['poin'],
            'gamification_level' => $this->resource['gamification_level'],
        ];
    }
}
