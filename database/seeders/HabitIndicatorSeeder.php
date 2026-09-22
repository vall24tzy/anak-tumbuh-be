<?php

namespace Database\Seeders;

use App\Models\Habit;
use App\Models\Indicator;
use Illuminate\Database\Seeder;

// DB Design v1.5 §5.10-§5.11, §11: seed FIXED 7 habit x 4 indicator (28 baris),
// dibaca dari database/seeders/data/indicators.php (satu file data, sesuai AC).
// Idempotent: updateOrCreate berdasarkan kolom UNIQUE (code / habit_id+sort_order).

class HabitIndicatorSeeder extends Seeder
{
    public function run(): void
    {
        $habits = require database_path('seeders/data/indicators.php');

        foreach ($habits as $habitData) {
            $habit = Habit::updateOrCreate(
                ['code' => $habitData['code']],
                [
                    'name' => $habitData['name'],
                    'allows_multi_select' => $habitData['allows_multi_select'],
                    'sort_order' => $habitData['sort_order'],
                ]
            );

            foreach ($habitData['indicators'] as $index => $indicatorData) {
                Indicator::updateOrCreate(
                    [
                        'habit_id' => $habit->id,
                        'sort_order' => $index + 1,
                    ],
                    [
                        'name' => $indicatorData['name'],
                        'point' => $indicatorData['point'],
                        'is_exclusive' => $indicatorData['is_exclusive'],
                    ]
                );
            }
        }
    }
}
