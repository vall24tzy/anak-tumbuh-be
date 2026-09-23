<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return match ($this->role) {
            'headmaster' => $this->staffProfile($this->headmaster),
            'teacher'    => $this->staffProfile($this->teacher),
            'student'    => [
                'id'     => $this->id,
                'role'   => $this->role,
                'name'   => $this->student?->fullname,
                'nisn'   => $this->student?->nisn,
                'school' => $this->schoolData($this->student?->school),
            ],
            default => [ // super_admin — belum punya tabel profil & belum punya school
                'id'   => $this->id,
                'role' => $this->role,
                'name' => $this->username,
            ],
        };
    }

    private function staffProfile($profile): array
    {
        return [
            'id'     => $this->id,
            'role'   => $this->role,
            'name'   => $profile?->fullname,
            'nip'    => $profile?->nip,
            'school' => $this->schoolData($profile?->school),
        ];
    }

    private function schoolData($school): ?array
    {
        return $school ? ['id' => $school->id, 'name' => $school->name] : null;
    }
}