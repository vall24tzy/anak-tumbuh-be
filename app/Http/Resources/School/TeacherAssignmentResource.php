<?php

namespace App\Http\Resources\School;

use Illuminate\Http\Resources\Json\JsonResource;

class TeacherAssignmentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'teacher_id'        => $this->teacher_id,
            'school_id'         => $this->school_id,
            'academic_year_id'  => $this->academic_year_id,
            'rombel_id'         => $this->rombel_id,
            'is_active'         => $this->is_active,
        ];
    }
}