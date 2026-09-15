<?php

namespace App\Http\Resources\Student;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class StudentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'fullname'    => $this->fullname,
            'nisn'        => $this->nisn,
            'gender'      => $this->gender,
            'classroom'   => $this->classroom,
            'grade_level' => $this->grade_level,
            'poin'        => $this->poin,
            'photo'       => $this->photo ? Storage::disk('public')->url($this->photo) : null,
            'school'      => [
                'id'   => $this->school->id,
                'name' => $this->school->name,
            ],
            'username'    => $this->user->username,
            'is_active'   => (bool) $this->user->is_active,
        ];
    }
}