<?php

namespace App\Http\Resources\School;

use Illuminate\Http\Resources\Json\JsonResource;

class RombelResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'school_id'         => $this->school_id,
            'academic_year_id'  => $this->academic_year_id,
            'name'              => $this->name,
            'grade_level'       => $this->grade_level,
        ];
    }
}