<?php

namespace App\Http\Resources\Habit;

use Illuminate\Http\Resources\Json\JsonResource;

class IndicatorResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'       => $this->id,
            'habit_id' => $this->habit_id,
            'name'     => $this->name,
            'poin'     => $this->poin,
        ];
    }
}