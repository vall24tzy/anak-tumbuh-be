<?php

namespace Database\Seeders;

use App\Models\GamificationLevel;
use Illuminate\Database\Seeder;

// DB Design v1.5 §5.14/§11: seed FIXED 101 baris (Level 0-100), dibaca dari
// database/seeders/data/gamification_levels.php (satu file data, angka persis
// dari dokumen "Analisis & Threshold Level ANAKTUMBUH" — tidak ada rumus/
// hardcode di kode). Idempotent: updateOrCreate berdasarkan PK level.

class GamificationLevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = require database_path('seeders/data/gamification_levels.php');

        foreach ($levels as $level => $requiredPoin) {
            GamificationLevel::updateOrCreate(
                ['level' => $level],
                ['required_poin' => $requiredPoin],
            );
        }
    }
}
