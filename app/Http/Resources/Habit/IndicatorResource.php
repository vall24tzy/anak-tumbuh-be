<?php

namespace App\Http\Resources\Habit;

use Illuminate\Http\Resources\Json\JsonResource;

class IndicatorResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'habit_id'     => $this->habit_id,
            'name'         => $this->name,
            'point'        => $this->point,
            'is_exclusive' => $this->is_exclusive,
            'sort_order'   => $this->sort_order,
        ];
    }
}
