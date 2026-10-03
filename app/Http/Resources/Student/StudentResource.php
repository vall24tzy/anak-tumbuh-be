<?php

namespace App\Http\Resources\Student;

use Illuminate\Http\Resources\Json\JsonResource;

// Field administratif FINAL (API Contract v1.2 §17): fullname, nisn, gender, photo.
// TIDAK ADA classroom/grade_level (Risk #1, DB Design v1.5) — current placement
// punya endpoint sendiri: GET /students/{id}/class-assignments.

class StudentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'       => $this->id,
            'fullname' => $this->fullname,
            'nisn'     => $this->nisn,
            'gender'   => $this->gender,
            // F-06: URL endpoint terotorisasi (butuh Authorization), bukan URL storage publik.
            'photo'    => $this->photo_path ? route('students.photo', ['id' => $this->id]) : null,
            'poin'     => $this->poin,
            'status'   => $this->status,
            'school'   => [
                'id'   => $this->school->id,
                'name' => $this->school->name,
            ],
            'username' => $this->user->username,
        ];
    }
}
