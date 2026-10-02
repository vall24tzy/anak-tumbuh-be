<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

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

        // F-30 (AUTH-003): 2 Super Admin aktif. Password awal dari konfigurasi deployment
        // (SEED_SUPER_ADMIN_PASSWORD -> config 'app.seed_super_admin_password'), bukan
        // literal di source. Akun yang sudah ada tidak diubah (setara firstOrCreate).
        foreach (['superadmin1', 'superadmin2'] as $username) {
            if (User::where('username', $username)->exists()) {
                continue;
            }

            User::create([
                'username' => $username,
                'role'     => 'super_admin',
                'password' => Hash::make($this->superAdminInitialPassword($username)),
            ]);
        }
    }

    /**
     * Konfigurasi kosong / hanya spasi dianggap TIDAK diset (bukan password kosong).
     * Tanpa konfigurasi: password acak per akun dan tidak pernah ditampilkan;
     * gunakan `php artisan admin:reset-password` untuk menetapkan password.
     */
    protected function superAdminInitialPassword(string $username): string
    {
        $configured = config('app.seed_super_admin_password');

        if (is_string($configured) && trim($configured) !== '') {
            return $configured;
        }

        $this->command?->warn(
            "SEED_SUPER_ADMIN_PASSWORD tidak diset: password acak dibuat untuk {$username}. "
            . 'Gunakan `php artisan admin:reset-password` untuk menetapkannya.'
        );

        return Str::random(32);
    }
}
