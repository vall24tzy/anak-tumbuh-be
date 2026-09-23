<?php

namespace App\Http\Resources\Student;

use Illuminate\Http\Resources\Json\JsonResource;

class StudentClassAssignmentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'student_id'        => $this->student_id,
            'school_id'         => $this->school_id,
            'rombel_id'         => $this->rombel_id,
            'academic_year_id'  => $this->academic_year_id,
            'status'            => $this->status,
            'created_at'        => $this->created_at,
        ];
    }
}
