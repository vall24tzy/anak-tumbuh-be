<?php

namespace Tests\Feature\Security;

use App\Support\CorsOrigins;
use Tests\TestCase;

// SECURITY-001 butir 3 - CORS dibatasi domain spesifik (env CORS_ALLOWED_ORIGINS), tanpa wildcard,
// tanpa localhost di production, method/header dibatasi ke yang dipakai API.
//
// STATUS EKSEKUSI: ditulis di sandbox tanpa PHP; jalankan `php artisan test --filter=CorsPolicyTest`.

class CorsPolicyTest extends TestCase
{
    private const ALLOWED = 'https://app.example.com';
    private const OTHER = 'https://admin.example.com';
    private const EVIL = 'https://evil.example.net';

    // Catatan: dengan SATU origin terdaftar, library CORS selalu mengirim origin itu apa adanya (browser yang menolak
    // bila tidak cocok). Dua origin terdaftar membuat library hanya mengirim header bila origin request cocok.

    // ---------- resolver (fungsi murni) ----------

    /** @test */
    public function production_tanpa_env_tidak_mengizinkan_origin_apa_pun(): void
    {
        $this->assertSame([], CorsOrigins::resolve(null, 'production'));
        $this->assertSame([], CorsOrigins::resolve('  , ,', 'production'));
    }

    /** @test */
    public function production_menerima_daftar_https_dibersihkan_dan_tanpa_duplikat(): void
    {
        $this->assertSame(
            ['https://app.example.com', 'https://admin.example.com:8443'],
            CorsOrigins::resolve(' https://app.example.com/ , https://admin.example.com:8443,https://app.example.com', 'production')
        );
    }

    /** @test */
    public function production_membuang_wildcard_http_localhost_dan_path(): void
    {
        $bad = '*,https://*.example.com,http://app.example.com,https://localhost:3000,https://app.localhost,'
             . 'https://app.example.com/path,https://app.example.com?x=1,app.example.com,null';

        $this->assertSame([], CorsOrigins::resolve($bad, 'production'));
    }

    /** @test */
    public function non_production_tanpa_env_memakai_default_localhost_dev(): void
    {
        $this->assertSame(CorsOrigins::DEV_DEFAULTS, CorsOrigins::resolve(null, 'local'));
        $this->assertSame(CorsOrigins::DEV_DEFAULTS, CorsOrigins::resolve('', 'testing'));
    }

    /** @test */
    public function non_production_dengan_env_hanya_memakai_env_tanpa_wildcard(): void
    {
        $this->assertSame(
            ['http://localhost:5173'],
            CorsOrigins::resolve('http://localhost:5173,*', 'local')
        );
    }

    // ---------- konfigurasi nyata ----------

    /** @test */
    public function config_cors_tidak_memakai_wildcard_dan_membatasi_method_dan_header(): void
    {
        $cors = config('cors');

        $this->assertNotContains('*', $cors['allowed_origins']);
        $this->assertNotContains('*', $cors['allowed_methods']);
        $this->assertNotContains('*', $cors['allowed_headers']);
        $this->assertEqualsCanonicalizing(['GET', 'POST', 'PATCH', 'DELETE', 'OPTIONS'], $cors['allowed_methods']);
        $this->assertContains('Authorization', $cors['allowed_headers']);
        $this->assertFalse($cors['supports_credentials']);
    }

    // ---------- perilaku HTTP ----------

    protected function preflight(string $origin)
    {
        return $this->call('OPTIONS', '/api/v1/auth/login/staff', [], [], [], [
            'HTTP_ORIGIN'                         => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD'  => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type,authorization',
        ]);
    }

    /** @test */
    public function preflight_dari_origin_terdaftar_mendapat_allow_origin_persis_origin_itu(): void
    {
        config(['cors.allowed_origins' => [self::ALLOWED, self::OTHER]]);

        $response = $this->preflight(self::ALLOWED);

        $this->assertSame(self::ALLOWED, $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    /** @test */
    public function preflight_dari_origin_tidak_terdaftar_tidak_mendapat_allow_origin(): void
    {
        config(['cors.allowed_origins' => [self::ALLOWED, self::OTHER]]);

        $this->assertNull($this->preflight(self::EVIL)->headers->get('Access-Control-Allow-Origin'));
    }

    /** @test */
    public function request_biasa_dari_origin_tidak_terdaftar_tidak_mendapat_allow_origin(): void
    {
        config(['cors.allowed_origins' => [self::ALLOWED, self::OTHER]]);

        $evil = $this->getJson('/api/v1/auth/me', ['Origin' => self::EVIL]);
        $good = $this->getJson('/api/v1/auth/me', ['Origin' => self::ALLOWED]);

        $this->assertNull($evil->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame(self::ALLOWED, $good->headers->get('Access-Control-Allow-Origin'));
    }
}
