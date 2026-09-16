<?php

namespace App\Http\Resources\Habit;

use Illuminate\Http\Resources\Json\JsonResource;

class HabitResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'indicators' => IndicatorResource::collection($this->whenLoaded('indicators')),
        ];
    }
}