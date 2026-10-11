<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

// SECURITY-001 butir 2 - .env.example adalah template produksi tanpa nilai rahasia nyata, dan
// secret (APP_KEY, JWT_SECRET) wajib dibuat per lingkungan. Test ini membaca berkas, bukan env aktif.
//
// STATUS EKSEKUSI: ditulis di sandbox tanpa PHP; jalankan `php artisan test --filter=EnvExampleSafetyTest`.

class EnvExampleSafetyTest extends TestCase
{
    /** @return array<string,string> */
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

    /** @test */
    public function template_produksi_debug_mati_dan_env_production(): void
    {
        $env = $this->envExample();

        $this->assertSame('false', $env['APP_DEBUG']);
        $this->assertSame('production', $env['APP_ENV']);
    }

    /** @test */
    public function app_key_dan_jwt_secret_ada_dan_kosong(): void
    {
        $env = $this->envExample();

        $this->assertArrayHasKey('APP_KEY', $env);
        $this->assertArrayHasKey('JWT_SECRET', $env);
        $this->assertSame('', $env['APP_KEY']);
        $this->assertSame('', $env['JWT_SECRET']);
    }

    /** @test */
    public function semua_variabel_bernuansa_rahasia_kosong_atau_null(): void
    {
        foreach ($this->envExample() as $key => $value) {
            if (preg_match('/(KEY|SECRET|PASSWORD|TOKEN)/', $key)) {
                $this->assertContains(
                    strtolower($value),
                    ['', 'null'],
                    ".env.example: {$key} berisi nilai (harus kosong/null): jangan commit secret"
                );
            }
        }
    }

    /** @test */
    public function cors_https_dan_proxy_terdokumentasi_tanpa_origin_nyata(): void
    {
        $env = $this->envExample();

        $this->assertArrayHasKey('CORS_ALLOWED_ORIGINS', $env);
        $this->assertSame('', $env['CORS_ALLOWED_ORIGINS']);
        $this->assertSame('true', $env['FORCE_HTTPS']);
        $this->assertArrayHasKey('TRUSTED_PROXIES', $env);
        $this->assertStringStartsWith('https://', $env['APP_URL']);
    }

    /** @test */
    public function berkas_env_asli_tidak_boleh_masuk_git(): void
    {
        $ignored = array_map('trim', file(base_path('.gitignore')));

        $this->assertContains('.env', $ignored);
        $this->assertContains('.env.production', $ignored);
    }
}
