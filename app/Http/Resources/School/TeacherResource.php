<?php

namespace App\Http\Resources\School;

use Illuminate\Http\Resources\Json\JsonResource;

class TeacherResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'        => $this->id,
            'school_id' => $this->school_id,
            'name'      => $this->fullname,
            'nip'       => $this->nip,
            'gender'    => $this->gender,
            'status'    => $this->status,
        ];
    }
}