<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// F-23 (Stage 2A) - rate limit login: DUA counter independen, per IP DAN per identifier,
// masing-masing 5 percobaan/menit. SoT: Matrix v3.1 F-23 (keputusan FINAL), F-47 (429 =
// `rate_limit_exceeded`). Counter tidak digabung menjadi satu kunci IP+identifier.
//
// Yang dikunci (dua bukti independen, sesuai AC):
//   1. SATU IP, identifier BERBEDA tiap request  -> request ke-6 = 429 (counter IP)
//   2. SATU identifier, IP BERBEDA tiap request  -> request ke-6 = 429 (counter identifier)
//   plus: kedua counter tidak saling memblokir pihak lain, ketiga route tercakup, bentuk
//   respons 429 mengikuti F-47.
//
// IP disimulasikan lewat REMOTE_ADDR (withServerVariables). Cache test = array, jadi counter
// dimulai dari nol di tiap test. Tidak ada test untuk identifier route QR: SoT belum
// menyatakan identifier QR (E-5); route QR hanya diuji pada counter IP.
//
// STATUS EKSEKUSI: dijalankan di PHP + PostgreSQL; hasil terkini lihat docs/testing/BASELINE-STAGE-0C.md

class LoginRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private const STAFF = '/api/v1/auth/login/staff';
    private const STUDENT_PASSWORD = '/api/v1/auth/login/student/password';
    private const STUDENT_QR = '/api/v1/auth/login/student/qr';

    protected function loginFrom(string $ip, string $uri, array $body)
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->postJson($uri, $body);
    }

    protected function assertRateLimited($response): void
    {
        $response->assertStatus(429)
            ->assertJsonPath('error.code', 'rate_limit_exceeded')
            ->assertJsonStructure(['error' => ['code', 'message', 'details']]);
    }

    protected function ip(int $n): string
    {
        return "10.20.30.{$n}";
    }

    /** @test */
    public function satu_ip_dengan_identifier_berbeda_tiap_request_kena_429_pada_request_ke_6(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->loginFrom($this->ip(1), self::STAFF, ['username' => "user{$i}", 'password' => 'salah'])->assertStatus(401);
        }

        $this->assertRateLimited($this->loginFrom($this->ip(1), self::STAFF, ['username' => 'user6', 'password' => 'salah']));
    }

    /** @test */
    public function satu_identifier_dari_ip_berbeda_tiap_request_kena_429_pada_request_ke_6(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->loginFrom($this->ip($i), self::STAFF, ['username' => 'target', 'password' => 'salah'])->assertStatus(401);
        }

        $this->assertRateLimited($this->loginFrom($this->ip(6), self::STAFF, ['username' => 'target', 'password' => 'salah']));
    }

    /** @test */
    public function counter_ip_yang_habis_tidak_memblokir_ip_lain_dengan_identifier_lain(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->loginFrom($this->ip(1), self::STAFF, ['username' => "a{$i}", 'password' => 'salah'])->assertStatus(401);
        }
        $this->assertRateLimited($this->loginFrom($this->ip(1), self::STAFF, ['username' => 'a6', 'password' => 'salah']));

        $this->loginFrom($this->ip(2), self::STAFF, ['username' => 'orang-lain', 'password' => 'salah'])->assertStatus(401);
    }

    /** @test */
    public function counter_identifier_yang_habis_tidak_memblokir_identifier_lain_dari_ip_lain(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->loginFrom($this->ip($i), self::STAFF, ['username' => 'target', 'password' => 'salah'])->assertStatus(401);
        }
        $this->assertRateLimited($this->loginFrom($this->ip(6), self::STAFF, ['username' => 'target', 'password' => 'salah']));

        $this->loginFrom($this->ip(7), self::STAFF, ['username' => 'identifier-lain', 'password' => 'salah'])->assertStatus(401);
    }

    /** @test */
    public function login_student_password_juga_dibatasi_per_nisn_dari_ip_berbeda(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->loginFrom($this->ip($i), self::STUDENT_PASSWORD, ['nisn' => '0051234567', 'password' => 'salah'])->assertStatus(401);
        }

        $this->assertRateLimited(
            $this->loginFrom($this->ip(6), self::STUDENT_PASSWORD, ['nisn' => '0051234567', 'password' => 'salah'])
        );
    }

    /** @test */
    public function login_student_password_dibatasi_per_ip_dengan_nisn_berbeda(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->loginFrom($this->ip(1), self::STUDENT_PASSWORD, ['nisn' => "00500000{$i}", 'password' => 'salah'])->assertStatus(401);
        }

        $this->assertRateLimited($this->loginFrom($this->ip(1), self::STUDENT_PASSWORD, ['nisn' => '0050000006', 'password' => 'salah']));
    }

    /** @test */
    public function login_student_qr_tetap_dibatasi_per_ip(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $status = $this->loginFrom($this->ip(1), self::STUDENT_QR, ['qr_payload' => "payload-{$i}"])->getStatusCode();
            $this->assertNotSame(429, $status, "request {$i} belum boleh kena limit");
        }

        $this->assertRateLimited($this->loginFrom($this->ip(1), self::STUDENT_QR, ['qr_payload' => 'payload-6']));
    }

    /** @test */
    public function lima_percobaan_wajar_tidak_terkena_limit_dan_login_berhasil_tetap_200(): void
    {
        $this->loginFrom($this->ip(1), self::STAFF, ['username' => 'tidak-ada', 'password' => 'salah'])->assertStatus(401);
        $this->loginFrom($this->ip(1), self::STAFF, ['username' => 'tidak-ada', 'password' => 'salah'])->assertStatus(401);

        \App\Models\User::create(['role' => 'super_admin', 'username' => 'sa_rl', 'password' => 'secret']);

        $this->loginFrom($this->ip(1), self::STAFF, ['username' => 'sa_rl', 'password' => 'secret'])->assertStatus(200);
    }
}
