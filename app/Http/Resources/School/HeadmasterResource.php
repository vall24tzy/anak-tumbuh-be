<?php

namespace App\Http\Resources\School;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class HeadmasterResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'        => $this->id,
            'fullname'  => $this->fullname,
            'nip'       => $this->nip,
            'gender'    => $this->gender,
            'photo'     => $this->photo ? Storage::disk('public')->url($this->photo) : null,
            'school'    => [
                'id'   => $this->school->id,
                'name' => $this->school->name,
            ],
            'username'  => $this->user->username,
            'email'     => $this->user->email,
            'is_active' => (bool) $this->user->is_active,
        ];
    }
}