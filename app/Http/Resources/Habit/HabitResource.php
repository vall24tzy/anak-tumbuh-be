<?php

namespace App\Http\Resources\Habit;

use Illuminate\Http\Resources\Json\JsonResource;

class HabitResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                   => $this->id,
            'code'                 => $this->code,
            'name'                 => $this->name,
            'allows_multi_select'  => $this->allows_multi_select,
            'sort_order'           => $this->sort_order,
            'indicators'           => IndicatorResource::collection($this->whenLoaded('indicators')),
        ];
    }
}
