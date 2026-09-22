<?php

namespace App\Services\Habit;

use App\Models\Habit;

// DB Design v1.5 §5.10-§5.11: Habit & Indicator FIXED, tidak ada write method.

class HabitService
{
    public function index()
    {
        return Habit::with('indicators')->orderBy('sort_order')->get();
    }
}
