<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Seeder;

// DB Design v1.5 (Fresh Deployment §10.A) — seeder pemanggilan test user lama
// (name/email) dihapus karena kolom tsb tidak ada lagi di skema users final.
// Coordinator ini sengaja dikosongkan di MIGRATION-002: seeding data milik
// task masing-masing —
//   - Super Admin pertama            -> AUTH-003
//   - habits + indicators (7x4 FIXED) -> HABIT-001 (lihat HabitIndicatorSeeder)
//   - gamification_levels (0-100)     -> LEVEL-RANKING-001
// Task-task tersebut wajib menambahkan pemanggilannya sendiri lewat $this->call([...])
// saat masing-masing dikerjakan, bukan menulis ulang di sini.

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            HabitIndicatorSeeder::class, // HABIT-001 — 7 habit x 4 indicator FIXED
            GamificationLevelSeeder::class, // LEVEL-RANKING-001 — threshold Level 0-100 (101 baris FIXED)
        ]);

        User::firstOrCreate(
            ['username' => 'superadmin1'],
            ['role' => 'super_admin', 'password' => Hash::make('ganti_ini_di_production')]
        );
        User::firstOrCreate(
            ['username' => 'superadmin2'],
            ['role' => 'super_admin', 'password' => Hash::make('ganti_ini_di_production')]
        );
    }
}
