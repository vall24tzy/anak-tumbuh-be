<?php

namespace Tests\Feature\Deploy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\SetsTemporaryEnv;
use Tests\TestCase;

// DEPLOY-RUNTIME-001 butir 4 dan 7 (sisi sebaliknya) - TRUSTED_PROXIES kosong: header proxy TIDAK dipercaya.
// Membuktikan dua hal yang hilang bila TRUSTED_PROXIES salah atau kosong di belakang proxy:
//   - X-Forwarded-Proto: https dari klien diabaikan (tidak bisa dipakai melewati EnforceHttps) -> tetap 308
//   - X-Forwarded-For diabaikan: semua request dari satu REMOTE_ADDR (alamat proxy) memakai SATU bucket rate limit,
//     jadi pengguna berbeda saling memblokir. Inilah gejala yang dijelaskan Task Board (semua pengguna terbaca
//     satu IP Vercel dan terkena 429 bersamaan) dan alasan TRUSTED_PROXIES wajib diisi di Vercel.
//
// TRUSTED_PROXIES diset kosong secara eksplisit (bukan dihapus) supaya nilai di .env dev tidak ikut terbaca.
//
// STATUS EKSEKUSI: dijalankan di PHP 8.3.30 (Laragon, Cmder): suite penuh 670 passed (3842 assertions), baik baseline maupun dengan DB_DISABLE_PREPARES=true.
// Ulangi dengan `php artisan test --filter=TrustedProxies`.

class TrustedProxiesDisabledTest extends TestCase
{
    use RefreshDatabase, SetsTemporaryEnv;

    private const PROXY = '10.0.0.1';

    protected function setUp(): void
    {
        $this->setEnv('TRUSTED_PROXIES', '');
        $this->flushTrustedProxies();

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->flushTrustedProxies();
        $this->restoreEnv();
    }

    protected function loginAs(string $clientIp, string $username)
    {
        return $this->withServerVariables(['REMOTE_ADDR' => self::PROXY])
            ->withHeaders(['X-Forwarded-For' => $clientIp])
            ->postJson('/api/v1/auth/login/staff', ['username' => $username, 'password' => 'salah']);
    }

    /** @test */
    public function x_forwarded_proto_https_dari_klien_diabaikan_dan_tetap_dialihkan_308(): void
    {
        config(['app.force_https' => true]);

        $response = $this->withServerVariables(['REMOTE_ADDR' => self::PROXY])
            ->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->get('/api/v1/auth/me');

        $response->assertStatus(308);
    }

    /** @test */
    public function x_forwarded_for_diabaikan_dan_ip_klien_adalah_alamat_pemanggil(): void
    {
        Route::get('/__proxy-probe', fn (Request $request) => response()->json(['ip' => $request->ip()]));

        $this->withServerVariables(['REMOTE_ADDR' => self::PROXY])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.7'])
            ->getJson('/__proxy-probe')
            ->assertOk()
            ->assertJsonPath('ip', self::PROXY);
    }

    /** @test */
    public function tanpa_proxy_tepercaya_klien_berbeda_berbagi_satu_bucket_dan_saling_memblokir(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->loginAs('203.0.113.10', "pengguna-{$i}")->assertStatus(401);
        }

        // Klien lain (X-Forwarded-For berbeda) ikut terkena 429 karena IP yang terbaca sama.
        $this->loginAs('203.0.113.11', 'pengguna-lain')
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'rate_limit_exceeded');
    }
}
