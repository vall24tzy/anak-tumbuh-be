<?php

namespace Tests\Feature\Deploy;

use Tests\Concerns\SetsTemporaryEnv;
use Tests\TestCase;

// DEPLOY-IMAGE-001 (D-26 usulan, PHP 8.3) - pemeriksaan STATIS berkas image dan konfigurasi runtime.
// Test ini membaca berkas di repo; TIDAK membangun atau menjalankan image (itu belum dilakukan, lihat addendum).
//
// Yang dikunci:
//   - composer.json mensyaratkan PHP ^8.3 dan composer.lock konsisten (platform.php sama)
//     -> gagal bila lupa menjalankan `composer update --lock` setelah menaikkan versi
//   - Dockerfile.vercel: base FrankenPHP PHP 8.3, ekstensi yang dibutuhkan, check-platform-reqs, log stderr,
//     TANPA menyalin .env, TANPA migrate/seed/config:cache, TANPA nilai rahasia di ENV
//   - .dockerignore mengecualikan .env, .git, vendor, tests, docs
//   - Caddyfile: root public/, php_server, TLS dihentikan di platform
//   - php.ini image: upload_max_filesize >= 5M (batas import 4 MB) dan post_max_size >= upload_max_filesize
//   - config/excel.php: file sementara Excel dapat diarahkan lewat EXCEL_TEMP_PATH, bagian config lain tetap
//   - docs/ops/vercel-deploy.md memuat setiap variabel di .env.example (nama saja)
//
// STATUS EKSEKUSI: ditulis di sandbox tanpa PHP; BELUM dijalankan. Jalankan
// `php artisan test --filter=ImageFilesTest`.

class ImageFilesTest extends TestCase
{
    use SetsTemporaryEnv;

    protected function read(string $path): string
    {
        $this->assertFileExists(base_path($path), "{$path} tidak ada");

        return (string) file_get_contents(base_path($path));
    }

    /** @return list<string> baris non-komentar dari berkas teks. */
    protected function activeLines(string $path): array
    {
        return array_values(array_filter(
            array_map('trim', explode("\n", $this->read($path))),
            fn (string $line) => $line !== '' && !str_starts_with($line, '#')
        ));
    }

    protected function bytes(string $value): int
    {
        $value = trim($value);
        $number = (int) $value;

        return match (strtoupper(substr($value, -1))) {
            'G' => $number * 1024 ** 3,
            'M' => $number * 1024 ** 2,
            'K' => $number * 1024,
            default => $number,
        };
    }

    // ---------- PHP 8.3 (D-26) ----------

    /** @test */
    public function composer_json_mensyaratkan_php_8_3_dan_composer_lock_konsisten(): void
    {
        $composer = json_decode($this->read('composer.json'), true, flags: JSON_THROW_ON_ERROR);
        $lock = json_decode($this->read('composer.lock'), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('^8.3', $composer['require']['php']);
        $this->assertSame(
            $composer['require']['php'],
            $lock['platform']['php'] ?? null,
            'composer.lock belum konsisten dengan composer.json: jalankan `composer update --lock` (hanya memperbarui hash dan platform)'
        );
    }

    // ---------- Dockerfile.vercel ----------

    /** @test */
    public function dockerfile_memakai_frankenphp_php_8_3_dan_memasang_ekstensi_yang_dibutuhkan(): void
    {
        $dockerfile = $this->read('Dockerfile.vercel');

        $this->assertMatchesRegularExpression('/^FROM dunglas\/frankenphp:\S*php8\.3\S*/m', $dockerfile);

        foreach (['pdo_pgsql', 'gd', 'zip', 'intl'] as $extension) {
            $this->assertMatchesRegularExpression(
                '/install-php-extensions[^\n]*\b' . preg_quote($extension, '/') . '\b/',
                $dockerfile,
                "ekstensi {$extension} tidak dipasang"
            );
        }

        $this->assertStringContainsString('composer check-platform-reqs --no-dev', $dockerfile);
    }

    /** @test */
    public function dockerfile_tanpa_menyalin_env_tanpa_migrate_atau_config_cache_dan_log_ke_stderr(): void
    {
        $dockerfile = $this->read('Dockerfile.vercel');
        $code = implode("\n", array_filter(
            array_map('trim', explode("\n", $dockerfile)),
            fn (string $line) => !str_starts_with($line, '#')
        ));

        // Tidak ada perintah yang menyalin berkas .env tertentu (COPY . . aman karena .dockerignore).
        $this->assertDoesNotMatchRegularExpression('/^(COPY|ADD)\s+[^\n]*\.env(?!\.example)\b/mi', $code);
        $this->assertStringNotContainsString('artisan migrate', $code);
        $this->assertStringNotContainsString('artisan db:seed', $code);
        $this->assertStringNotContainsString('config:cache', $code);
        $this->assertStringContainsString('LOG_CHANNEL=stderr', $code);
    }

    /** @test */
    public function dockerfile_tidak_memuat_nilai_rahasia_di_env(): void
    {
        $dockerfile = $this->read('Dockerfile.vercel');

        $this->assertDoesNotMatchRegularExpression(
            '/\b(APP_KEY|JWT_SECRET|DB_PASSWORD|DB_URL|AWS_SECRET_ACCESS_KEY|AWS_ACCESS_KEY_ID|SEED_SUPER_ADMIN_PASSWORD)\s*=\s*\S/',
            $dockerfile,
            'Dockerfile.vercel tidak boleh memuat nilai rahasia'
        );
    }

    // ---------- .dockerignore, Caddyfile, php.ini ----------

    /** @test */
    public function dockerignore_mengecualikan_env_git_vendor_tests_dan_docs(): void
    {
        $lines = $this->activeLines('.dockerignore');

        foreach (['.env', '.env.*', '!.env.example', '.git', 'vendor', 'node_modules', 'tests', 'docs'] as $expected) {
            $this->assertContains($expected, $lines, ".dockerignore: {$expected} tidak ada");
        }

        // Pengecualian .env.example harus SETELAH pola .env.* supaya berlaku.
        $this->assertGreaterThan(array_search('.env.*', $lines, true), array_search('!.env.example', $lines, true));
    }

    /** @test */
    public function caddyfile_melayani_public_dengan_php_server_tanpa_tls_sendiri(): void
    {
        $caddyfile = $this->read('Caddyfile');

        $this->assertStringContainsString('root * /app/public', $caddyfile);
        $this->assertStringContainsString('php_server', $caddyfile);
        $this->assertStringContainsString('auto_https off', $caddyfile);
    }

    /** @test */
    public function php_ini_image_memberi_ruang_di_atas_batas_import_4_mb(): void
    {
        $ini = parse_ini_string($this->read('docker/php/zz-vercel.ini'), false, INI_SCANNER_RAW);

        $this->assertIsArray($ini);
        $this->assertGreaterThanOrEqual(5 * 1024 ** 2, $this->bytes($ini['upload_max_filesize']));
        $this->assertGreaterThanOrEqual($this->bytes($ini['upload_max_filesize']), $this->bytes($ini['post_max_size']));
        $this->assertSame('/tmp', $ini['upload_tmp_dir']);
        $this->assertSame('Off', $ini['display_errors']);
    }

    // ---------- config/excel.php (butir 8b) ----------

    /** @test */
    public function file_sementara_excel_bisa_diarahkan_lewat_env_dan_default_tidak_berubah(): void
    {
        $default = $this->configWithEnv('excel', ['EXCEL_TEMP_PATH' => null]);
        $this->assertSame(storage_path('framework/cache/laravel-excel'), $default['temporary_files']['local_path']);

        $custom = $this->configWithEnv('excel', ['EXCEL_TEMP_PATH' => '/tmp/laravel-excel']);
        $this->assertSame('/tmp/laravel-excel', $custom['temporary_files']['local_path']);

        // Seluruh kunci bagian temporary_files ada (paket menggabungkan config hanya satu tingkat).
        foreach (['local_path', 'local_permissions', 'remote_disk', 'remote_prefix', 'force_resync_remote'] as $key) {
            $this->assertArrayHasKey($key, $default['temporary_files'], $key);
        }
    }

    /** @test */
    public function config_excel_aktif_menimpa_hanya_temporary_files_dan_bagian_lain_tetap_bawaan_paket(): void
    {
        $this->assertSame(
            env('EXCEL_TEMP_PATH', storage_path('framework/cache/laravel-excel')),
            config('excel.temporary_files.local_path')
        );
        $this->assertNotNull(config('excel.exports'), 'bagian exports bawaan paket hilang setelah config/excel.php ditambahkan');
        $this->assertNotNull(config('excel.imports'), 'bagian imports bawaan paket hilang setelah config/excel.php ditambahkan');
    }

    // ---------- dokumen env ----------

    /** @test */
    public function dokumen_vercel_deploy_memuat_setiap_variabel_env_example_dan_menyatakan_utc(): void
    {
        $doc = $this->read('docs/ops/vercel-deploy.md');

        foreach (file(base_path('.env.example'), FILE_IGNORE_NEW_LINES) as $line) {
            if (preg_match('/^#?\s*([A-Z][A-Z0-9_]*)=/', trim($line), $match)) {
                $this->assertStringContainsString($match[1], $doc, "vercel-deploy.md tidak menyebut {$match[1]}");
            }
        }

        $this->assertMatchesRegularExpression('/APP_TIMEZONE[^\n]*UTC/', $doc);
    }
}
