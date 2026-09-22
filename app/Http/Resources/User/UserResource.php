<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'   => $this->id,
            'role' => $this->role,
            'name' => $this->resolveName(),
        ];
    }

    private function resolveName(): ?string
    {
        return match ($this->role) {
            'headmaster' => $this->headmaster?->fullname,
            'teacher'    => $this->teacher?->fullname,
            'student'    => $this->student?->fullname,
            default      => $this->username, // super_admin belum punya tabel profil sendiri
        };
    }
}