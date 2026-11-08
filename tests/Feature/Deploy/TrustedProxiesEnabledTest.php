<?php

namespace Tests\Feature\Deploy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\SetsTemporaryEnv;
use Tests\TestCase;

// DEPLOY-RUNTIME-001 butir 4 dan 7 - aplikasi di belakang proxy Vercel dengan TRUSTED_PROXIES diisi.
// SoT: Task Board Deployment v1.2 (DEPLOY-RUNTIME-001), SECURITY-001 butir 4 (EnforceHttps), F-23 (rate limit login).
//
// Yang dikunci (TRUSTED_PROXIES='*' = percaya proxy yang memanggil, pola Vercel yang tidak punya IP tetap):
//   - request dengan X-Forwarded-Proto: https dari proxy tepercaya TIDAK dialihkan dan membawa HSTS
//   - request http biasa (tanpa header) dan X-Forwarded-Proto: http tetap ditolak 308
//   - IP klien dibaca dari X-Forwarded-For milik proxy tepercaya
//   - klien dengan X-Forwarded-For berbeda memakai bucket rate limit berbeda; satu klien yang kena 429
//     tidak memblokir klien lain, dan kedua klien dihitung independen saat bergantian
//
// Kelas pasangannya, TrustedProxiesDisabledTest, membuktikan sisi sebaliknya (header diabaikan bila tidak tepercaya).
// TRUSTED_PROXIES diset SEBELUM aplikasi dibuat karena bootstrap/app.php membacanya saat boot; proxy tepercaya
// disimpan Laravel di properti static, jadi di-flush sebelum dan sesudah test.
//
// STATUS EKSEKUSI: dijalankan di PHP 8.3.30 (Laragon, Cmder): suite penuh 670 passed (3842 assertions), baik baseline maupun dengan DB_DISABLE_PREPARES=true.
// Ulangi dengan `php artisan test --filter=TrustedProxies`.

class TrustedProxiesEnabledTest extends TestCase
{
    use RefreshDatabase, SetsTemporaryEnv;

    private const ME = '/api/v1/auth/me';
    private const LOGIN = '/api/v1/auth/login/staff';
    private const PROXY = '10.0.0.1';

    protected function setUp(): void
    {
        $this->setEnv('TRUSTED_PROXIES', '*');
        $this->flushTrustedProxies();

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->flushTrustedProxies();
        $this->restoreEnv();
    }

    /**
     * Request lewat proxy tepercaya; $clientIp mengisi X-Forwarded-For.
     *
     * withHeaders() pada test client Laravel MENUMPUK header ke request berikutnya dalam satu test, jadi header
     * dibersihkan lebih dulu; tanpa ini request "tanpa X-Forwarded-For" masih membawa nilai request sebelumnya.
     */
    protected function viaProxy(?string $clientIp = null, array $headers = [])
    {
        $this->flushHeaders();

        if ($clientIp !== null) {
            $headers['X-Forwarded-For'] = $clientIp;
        }

        return $this->withServerVariables(['REMOTE_ADDR' => self::PROXY])->withHeaders($headers);
    }

    protected function loginAs(string $clientIp, string $username)
    {
        return $this->viaProxy($clientIp)->postJson(self::LOGIN, ['username' => $username, 'password' => 'salah']);
    }

    // ---------- EnforceHttps di belakang proxy ----------

    /** @test */
    public function x_forwarded_proto_https_dari_proxy_tepercaya_tidak_dialihkan_dan_membawa_hsts(): void
    {
        config(['app.force_https' => true]);

        $response = $this->viaProxy(null, ['X-Forwarded-Proto' => 'https'])->getJson(self::ME);

        $response->assertStatus(401); // sampai ke aplikasi, bukan 308
        $this->assertNotNull($response->headers->get('Strict-Transport-Security'));
    }

    /** @test */
    public function request_http_tanpa_header_proxy_tetap_ditolak_308(): void
    {
        config(['app.force_https' => true]);

        $response = $this->viaProxy()->get(self::ME);

        $response->assertStatus(308);
        $this->assertStringStartsWith('https://', (string) $response->headers->get('Location'));
    }

    /** @test */
    public function x_forwarded_proto_http_dari_proxy_tetap_ditolak_308(): void
    {
        config(['app.force_https' => true]);

        $this->viaProxy(null, ['X-Forwarded-Proto' => 'http'])->get(self::ME)->assertStatus(308);
    }

    // ---------- IP klien dan rate limit login per IP ----------

    /** @test */
    public function ip_klien_dibaca_dari_x_forwarded_for_proxy_tepercaya(): void
    {
        Route::get('/__proxy-probe', fn (Request $request) => response()->json(['ip' => $request->ip()]));

        $this->viaProxy('203.0.113.7')->getJson('/__proxy-probe')->assertOk()->assertJsonPath('ip', '203.0.113.7');
        $this->viaProxy('203.0.113.8')->getJson('/__proxy-probe')->assertOk()->assertJsonPath('ip', '203.0.113.8');

        // Tanpa X-Forwarded-For: jatuh ke alamat proxy yang memanggil.
        $this->viaProxy()->getJson('/__proxy-probe')->assertOk()->assertJsonPath('ip', self::PROXY);
    }

    /** @test */
    public function klien_dengan_x_forwarded_for_berbeda_memakai_bucket_berbeda_dan_tidak_saling_memblokir(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->loginAs('203.0.113.10', "klien-a-{$i}")->assertStatus(401);
        }

        // Klien A habis kuota IP (5/menit): percobaan ke-6 = 429.
        $this->loginAs('203.0.113.10', 'klien-a-6')
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'rate_limit_exceeded');

        // Klien B memakai proxy yang SAMA tetapi IP klien berbeda: tidak ikut terblokir.
        $this->loginAs('203.0.113.11', 'klien-b-1')->assertStatus(401);
    }

    /** @test */
    public function dua_klien_bergantian_dihitung_independen(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->loginAs('203.0.113.20', "bergantian-a-{$i}")->assertStatus(401);
            $this->loginAs('203.0.113.21', "bergantian-b-{$i}")->assertStatus(401);
        }

        $this->loginAs('203.0.113.20', 'bergantian-a-6')->assertStatus(429);
        $this->loginAs('203.0.113.21', 'bergantian-b-6')->assertStatus(429);
        $this->loginAs('203.0.113.22', 'bergantian-c-1')->assertStatus(401);
    }
}
