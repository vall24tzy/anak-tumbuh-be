<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

// F-30 (Stage 2A) - password awal Super Admin berasal dari konfigurasi deployment
// (SEED_SUPER_ADMIN_PASSWORD -> config 'app.seed_super_admin_password'), bukan literal di
// source. SoT: Matrix v3.1 F-30 + E-6 (keputusan FINAL; konfigurasi tidak diset = TIDAK
// membuat password default yang dikenal), Task Board AUTH-003 (seed minimal 2 akun),
// Authorization Matrix #47 (Change Own Password SA tetap ALLOW).
//
// Nilai password di test ini dibangkitkan acak di dalam test (tidak ada literal password).
//
// STATUS EKSEKUSI: dijalankan di PHP + PostgreSQL; hasil terkini lihat docs/testing/BASELINE-STAGE-0C.md

class SuperAdminSeedTest extends TestCase
{
    use RefreshDatabase;

    protected function seedWith($configured): void
    {
        config(['app.seed_super_admin_password' => $configured]);

        $this->seed(DatabaseSeeder::class);
    }

    protected function superAdmins()
    {
        return User::where('role', 'super_admin')->whereIn('username', ['superadmin1', 'superadmin2'])->orderBy('username')->get();
    }

    /** @test */
    public function seeder_memakai_password_dari_konfigurasi_untuk_kedua_super_admin(): void
    {
        $password = 'T-' . Str::random(24);

        $this->seedWith($password);

        $admins = $this->superAdmins();
        $this->assertCount(2, $admins);
        foreach ($admins as $admin) {
            $this->assertTrue(Hash::check($password, $admin->password), "password {$admin->username} berasal dari konfigurasi");
        }
    }

    /** @test */
    public function seeder_tidak_gagal_tanpa_konfigurasi_dan_membuat_password_acak_berbeda_per_akun(): void
    {
        $this->seedWith(null);

        $admins = $this->superAdmins();
        $this->assertCount(2, $admins);
        $this->assertNotSame($admins[0]->password, $admins[1]->password);

        $probe = 'T-' . Str::random(24);
        foreach ($admins as $admin) {
            $this->assertFalse(Hash::check($probe, $admin->password));
            $this->assertFalse(Hash::check('', $admin->password), 'password kosong tidak boleh berlaku');
        }
    }

    /**
     * @test
     * @dataProvider blankConfigurations
     */
    public function konfigurasi_kosong_atau_spasi_dianggap_tidak_diset_bukan_password_kosong($blank): void
    {
        $this->seedWith($blank);

        foreach ($this->superAdmins() as $admin) {
            $this->assertFalse(Hash::check($blank, $admin->password), 'konfigurasi kosong tidak boleh menjadi password');
            $this->assertFalse(Hash::check('', $admin->password));
        }
        $this->assertCount(2, $this->superAdmins());
    }

    public static function blankConfigurations(): array
    {
        return [
            'string kosong' => [''],
            'hanya spasi'   => ['   '],
        ];
    }

    /** @test */
    public function menjalankan_seeder_ulang_tidak_menimpa_password_akun_yang_sudah_ada(): void
    {
        $first = 'T-' . Str::random(24);
        $second = 'T-' . Str::random(24);

        $this->seedWith($first);
        $this->seedWith($second);

        $this->assertCount(2, $this->superAdmins());
        foreach ($this->superAdmins() as $admin) {
            $this->assertTrue(Hash::check($first, $admin->password));
            $this->assertFalse(Hash::check($second, $admin->password));
        }
    }

    /**
     * Penjaga statis: nilai acak per-hash tidak bisa membuktikan ketiadaan password default,
     * jadi source seeder dipindai agar tidak ada Hash::make('literal') dan .env.example
     * tidak memuat nilai password awal.
     * @test
     */
    public function source_seeder_dan_env_example_tidak_memuat_password_plaintext(): void
    {
        foreach (glob(database_path('seeders/*.php')) as $file) {
            $this->assertDoesNotMatchRegularExpression(
                "/Hash::make\(\s*['\"]/",
                file_get_contents($file),
                basename($file) . ' tidak boleh memakai Hash::make dengan string literal'
            );
        }

        $example = file_get_contents(base_path('.env.example'));
        $this->assertMatchesRegularExpression('/^SEED_SUPER_ADMIN_PASSWORD=\s*$/m', $example, 'nilai SEED_SUPER_ADMIN_PASSWORD di .env.example harus kosong');
    }

    /** @test */
    public function super_admin_hasil_seed_bisa_login_dan_mengganti_password_sendiri(): void
    {
        $password = 'T-' . Str::random(24);
        $newPassword = 'N-' . Str::random(24);
        $this->seedWith($password);

        $login = $this->postJson('/api/v1/auth/login/staff', ['username' => 'superadmin1', 'password' => $password]);
        $login->assertStatus(200);

        auth()->forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $login->json('data.access_token'))
            ->postJson('/api/v1/auth/change-password', ['new_password' => $newPassword])
            ->assertSuccessful();

        $this->assertTrue(Hash::check($newPassword, User::where('username', 'superadmin1')->value('password')));
    }
}
