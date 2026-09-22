<?php

namespace App\Http\Resources\School;

use Illuminate\Http\Resources\Json\JsonResource;

class AcademicYearResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'school_id'  => $this->school_id,
            'name'       => $this->name,
            'start_date' => $this->start_date->format('Y-m-d'),
            'end_date'   => $this->end_date->format('Y-m-d'),
            'is_active'  => $this->is_active,
        ];
    }
}