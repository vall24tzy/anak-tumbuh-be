<?php

namespace App\Http\Resources\School;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class SchoolResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'address'        => $this->address,
            'logo'           => $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null,
            'teachers_count' => $this->whenCounted('teachers'),
            'students_count' => $this->whenCounted('students'),
        ];
    }
}