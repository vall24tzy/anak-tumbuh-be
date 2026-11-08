<?php

namespace Tests\Feature\Deploy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\Concerns\SetsTemporaryEnv;
use Tests\TestCase;

// DEPLOY-RUNTIME-001 (D-27, usulan) - konfigurasi koneksi PostgreSQL untuk pooler Supabase.
// SoT: Task Board Deployment v1.2 (DEPLOY-RUNTIME-001 butir 1, 2, 5, 8), keputusan D-27 (BELUM final).
//
// Yang dikunci (level KONFIGURASI; perilaku nyata dengan flag hidup dibuktikan lewat suite penuh, lihat addendum):
//   - flag DB_EMULATE_PREPARES default MATI dan hanya berlaku untuk koneksi `pgsql` (aplikasi), bukan `pgsql_direct`
//   - nilai env diterjemahkan benar ('true', '1', 'on' = hidup; 'false', '0', 'off', 'no' = mati)
//   - `pgsql_direct` ada, jatuh ke DB_* bila DB_DIRECT_* kosong, memakai DB_DIRECT_* bila diisi, dan TIDAK
//     mewarisi DB_URL (supaya migrate tidak diam-diam lewat pooler)
//   - sslmode dan sslrootcert diatur lewat env, default tetap 'prefer'
//   - `pgsql_direct` benar-benar bisa dipakai (query dan `migrate:status --database=pgsql_direct`)
//   - .env.example: nilai default aman, CACHE_STORE=database, APP_TIMEZONE=UTC; config/app.php default UTC
//
// STATUS EKSEKUSI: dijalankan di PHP 8.3.30 (Laragon, Cmder): suite penuh 670 passed (3842 assertions), baik baseline maupun dengan DB_DISABLE_PREPARES=true.
// Ulangi dengan `php artisan test --filter=DatabaseConnectionConfigTest`.

class DatabaseConnectionConfigTest extends TestCase
{
    use RefreshDatabase, SetsTemporaryEnv;

    /** Variabel yang dikosongkan dulu supaya hasil tidak bergantung pada env mesin (mis. run suite dengan flag hidup). */
    private const DB_ENV = [
        'DB_URL', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_SSLMODE', 'DB_SSLROOTCERT',
        'DB_EMULATE_PREPARES', 'DB_DISABLE_PREPARES', 'DB_DIRECT_URL', 'DB_DIRECT_HOST', 'DB_DIRECT_PORT', 'DB_DIRECT_DATABASE',
        'DB_DIRECT_USERNAME', 'DB_DIRECT_PASSWORD', 'DB_DIRECT_SSLMODE', 'DB_DIRECT_SSLROOTCERT',
    ];

    /** @return array<string, mixed> isi config('database.connections') dengan env tertentu. */
    protected function connections(array $env = []): array
    {
        return $this->configWithEnv('database', array_merge(array_fill_keys(self::DB_ENV, null), $env))['connections'];
    }

    /** @return array<string, string> isi .env.example (kunci => nilai), baris komentar dilewati. */
    protected function envExample(): array
    {
        $values = [];

        foreach (file(base_path('.env.example'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $values[trim($key)] = trim($value);
        }

        return $values;
    }

    // ---------- flag emulate prepares (D-27) ----------

    /** @test */
    public function emulate_prepares_default_mati_bila_env_tidak_diisi(): void
    {
        $options = $this->connections()['pgsql']['options'];

        $this->assertArrayHasKey(PDO::ATTR_EMULATE_PREPARES, $options);
        $this->assertFalse($options[PDO::ATTR_EMULATE_PREPARES]);
    }

    /** @test */
    public function nilai_env_flag_diterjemahkan_benar(): void
    {
        $cases = [
            'true' => true, '1' => true, 'on' => true, 'yes' => true,
            'false' => false, '0' => false, 'off' => false, 'no' => false,
        ];

        foreach ($cases as $value => $expected) {
            $options = $this->connections(['DB_EMULATE_PREPARES' => (string) $value])['pgsql']['options'];

            $this->assertSame($expected, $options[PDO::ATTR_EMULATE_PREPARES], "DB_EMULATE_PREPARES={$value}");
        }
    }

    /** @test */
    public function pgsql_direct_tidak_memakai_opsi_emulate_walau_flag_hidup(): void
    {
        $connections = $this->connections(['DB_EMULATE_PREPARES' => 'true']);

        $this->assertTrue($connections['pgsql']['options'][PDO::ATTR_EMULATE_PREPARES]);
        $this->assertArrayNotHasKey('options', $connections['pgsql_direct']);
    }

    /** @test */
    public function disable_prepares_default_mati_dan_hanya_berlaku_untuk_pgsql_bukan_pgsql_direct(): void
    {
        $default = $this->connections();
        $this->assertArrayNotHasKey(PDO::PGSQL_ATTR_DISABLE_PREPARES, $default['pgsql']['options']);

        $on = $this->connections(['DB_DISABLE_PREPARES' => 'true']);
        $this->assertTrue($on['pgsql']['options'][PDO::PGSQL_ATTR_DISABLE_PREPARES]);
        $this->assertFalse($on['pgsql']['options'][PDO::ATTR_EMULATE_PREPARES]); // dua opsi independen
        $this->assertArrayNotHasKey('options', $on['pgsql_direct']);

        $off = $this->connections(['DB_DISABLE_PREPARES' => 'off']);
        $this->assertArrayNotHasKey(PDO::PGSQL_ATTR_DISABLE_PREPARES, $off['pgsql']['options']);
    }

    // ---------- pgsql_direct ----------

    /** @test */
    public function pgsql_direct_ada_dan_memakai_driver_pgsql(): void
    {
        $direct = $this->connections()['pgsql_direct'];

        $this->assertSame('pgsql', $direct['driver']);
    }

    /** @test */
    public function pgsql_direct_jatuh_ke_nilai_db_bila_db_direct_kosong(): void
    {
        $connections = $this->connections([
            'DB_HOST' => 'db.lokal', 'DB_PORT' => '5433', 'DB_DATABASE' => 'app_db',
            'DB_USERNAME' => 'app_user', 'DB_PASSWORD' => 'rahasia-uji',
        ]);

        foreach (['host', 'port', 'database', 'username', 'password'] as $key) {
            $this->assertSame($connections['pgsql'][$key], $connections['pgsql_direct'][$key], $key);
        }
        $this->assertSame('db.lokal', $connections['pgsql_direct']['host']);
    }

    /** @test */
    public function pgsql_direct_memakai_db_direct_bila_diisi_dan_pgsql_tidak_berubah(): void
    {
        $connections = $this->connections([
            'DB_HOST' => 'pooler.lokal', 'DB_USERNAME' => 'postgres.proyek', 'DB_PORT' => '6543',
            'DB_DIRECT_HOST' => 'direct.lokal', 'DB_DIRECT_USERNAME' => 'postgres', 'DB_DIRECT_PORT' => '5432',
            'DB_DIRECT_DATABASE' => 'postgres', 'DB_DIRECT_PASSWORD' => 'rahasia-direct',
        ]);

        $this->assertSame('pooler.lokal', $connections['pgsql']['host']);
        $this->assertSame('postgres.proyek', $connections['pgsql']['username']);
        $this->assertSame('direct.lokal', $connections['pgsql_direct']['host']);
        $this->assertSame('postgres', $connections['pgsql_direct']['username']);
        $this->assertSame('5432', (string) $connections['pgsql_direct']['port']);
        $this->assertSame('postgres', $connections['pgsql_direct']['database']);
        $this->assertSame('rahasia-direct', $connections['pgsql_direct']['password']);
    }

    /** @test */
    public function db_url_hanya_untuk_pgsql_dan_tidak_diwarisi_pgsql_direct(): void
    {
        $connections = $this->connections(['DB_URL' => 'postgresql://u:p@pooler.lokal:6543/postgres']);

        $this->assertSame('postgresql://u:p@pooler.lokal:6543/postgres', $connections['pgsql']['url']);
        $this->assertNull($connections['pgsql_direct']['url']);

        $connections = $this->connections([
            'DB_URL' => 'postgresql://u:p@pooler.lokal:6543/postgres',
            'DB_DIRECT_URL' => 'postgresql://u:p@direct.lokal:5432/postgres',
        ]);

        $this->assertSame('postgresql://u:p@direct.lokal:5432/postgres', $connections['pgsql_direct']['url']);
    }

    // ---------- SSL ----------

    /** @test */
    public function sslmode_default_prefer_dan_bisa_diatur_lewat_env(): void
    {
        $default = $this->connections();
        $this->assertSame('prefer', $default['pgsql']['sslmode']);
        $this->assertSame('prefer', $default['pgsql_direct']['sslmode']);
        $this->assertNull($default['pgsql']['sslrootcert']);

        $require = $this->connections(['DB_SSLMODE' => 'require', 'DB_SSLROOTCERT' => '/certs/ca.crt']);
        $this->assertSame('require', $require['pgsql']['sslmode']);
        $this->assertSame('require', $require['pgsql_direct']['sslmode']);
        $this->assertSame('/certs/ca.crt', $require['pgsql']['sslrootcert']);
        $this->assertSame('/certs/ca.crt', $require['pgsql_direct']['sslrootcert']);

        $override = $this->connections(['DB_SSLMODE' => 'require', 'DB_DIRECT_SSLMODE' => 'verify-full']);
        $this->assertSame('require', $override['pgsql']['sslmode']);
        $this->assertSame('verify-full', $override['pgsql_direct']['sslmode']);
    }

    // ---------- koneksi nyata ----------

    /** @test */
    public function pgsql_direct_bisa_dipakai_untuk_query(): void
    {
        $row = DB::connection('pgsql_direct')->selectOne('select 1 as ok');

        $this->assertSame(1, (int) $row->ok);
    }

    /** @test */
    public function artisan_migrate_status_menerima_database_pgsql_direct(): void
    {
        $this->artisan('migrate:status', ['--database' => 'pgsql_direct'])->assertExitCode(0);
    }

    // ---------- .env.example dan timezone ----------

    /** @test */
    public function env_example_default_aman_tanpa_rahasia_untuk_koneksi_database(): void
    {
        $env = $this->envExample();

        $this->assertSame('false', $env['DB_EMULATE_PREPARES']);
        $this->assertSame('prefer', $env['DB_SSLMODE']);

        // DB_URL dan DB_DIRECT_* hanya boleh ada sebagai komentar (nilainya diisi di environment platform).
        foreach (array_keys($env) as $key) {
            $this->assertFalse(
                $key === 'DB_URL' || str_starts_with($key, 'DB_DIRECT_'),
                ".env.example: {$key} tidak boleh aktif (hanya sebagai komentar tanpa nilai)"
            );
        }
    }

    /** @test */
    public function cache_store_database_wajib_dan_timezone_tetap_utc_di_env_example(): void
    {
        $env = $this->envExample();

        $this->assertSame('database', $env['CACHE_STORE']); // blacklist JWT dan rate limit login memakai cache
        $this->assertSame('UTC', $env['APP_TIMEZONE']);      // Contract v1.3 bagian 7: timestamp UTC
    }

    /** @test */
    public function default_timezone_aplikasi_utc_bila_env_tidak_diisi(): void
    {
        $app = $this->configWithEnv('app', ['APP_TIMEZONE' => null]);

        $this->assertSame('UTC', $app['timezone']);
    }
}
