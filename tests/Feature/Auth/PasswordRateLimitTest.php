<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreatesSchoolFixtures;
use Tests\TestCase;

// SECURITY-001 butir 5 (keputusan pemilik): limiter `password-change`, kunci = ID user penggugah (jwt),
// 10 request/menit, tanpa IP. Dipasang di 4 route: headmasters/{id}/reset-password, teachers/{id}/reset-password,
// students/{id}/reset-password, auth/change-password. Pola F-23 / F-47 (429 `rate_limit_exceeded`). Login tidak diubah.
//
// Request ke ID yang tidak ada (404) dan body tidak valid (422) tetap dihitung: limiter berjalan sebelum controller.
//
// STATUS EKSEKUSI: ditulis di sandbox tanpa PHP; jalankan `php artisan test --filter=PasswordRateLimitTest`.

class PasswordRateLimitTest extends TestCase
{
    use RefreshDatabase, CreatesSchoolFixtures;

    private const LIMITER = 'throttle.password';

    protected function routeMiddleware(string $uriSuffix): array
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (in_array('POST', $route->methods(), true) && str_ends_with($route->uri(), $uriSuffix)) {
                return $route->gatherMiddleware();
            }
        }

        $this->fail("Route POST *{$uriSuffix} tidak ditemukan");
    }

    /** Kirim 10 request: tidak ada yang 429; request ke-11 = 429 rate_limit_exceeded. */
    protected function assertEleventhIs429(string $token, string $uri, array $body = []): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->assertNotSame(429, $this->apiPost($uri, $token, $body)->getStatusCode(), "request ke-{$i} sudah 429");
        }

        $this->apiPost($uri, $token, $body)
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'rate_limit_exceeded');
    }

    /** @test */
    public function limiter_terpasang_di_keempat_route_dan_tidak_di_login(): void
    {
        foreach (['/headmasters/{id}/reset-password', '/teachers/{id}/reset-password', '/students/{id}/reset-password', '/auth/change-password'] as $suffix) {
            $this->assertContains(self::LIMITER, $this->routeMiddleware($suffix), "limiter tidak ada di {$suffix}");
        }

        foreach (['/auth/login/staff', '/auth/login/student/qr'] as $suffix) {
            $this->assertNotContains(self::LIMITER, $this->routeMiddleware($suffix), "login {$suffix} tidak boleh berubah");
        }
    }

    /** @test */
    public function reset_password_headmaster_super_admin_request_ke_11_kena_429(): void
    {
        $this->assertEleventhIs429($this->fxToken($this->fxSuperAdmin()), '/api/v1/headmasters/999999999/reset-password', ['new_password' => 'PasswordBaru_2026']);
    }

    /** @test */
    public function reset_password_teacher_headmaster_request_ke_11_kena_429(): void
    {
        $school = $this->fxSchool();
        $hm = $this->fxHeadmaster($school, '1001');

        $this->assertEleventhIs429($this->fxToken($hm), '/api/v1/teachers/999999999/reset-password', ['new_password' => 'PasswordBaru_2026']);
    }

    /** @test */
    public function reset_password_student_teacher_request_ke_11_kena_429(): void
    {
        $school = $this->fxSchool();
        $teacher = $this->fxTeacher($school, '2001');

        $this->assertEleventhIs429($this->fxToken($teacher), '/api/v1/students/999999999/reset-password', ['new_password' => 'PasswordBaru_2026']);
    }

    /** @test */
    public function change_password_super_admin_request_ke_11_kena_429(): void
    {
        // Body tidak valid (422) supaya password tidak berubah dan token tidak dicabut (F-29); tetap dihitung limiter.
        $this->assertEleventhIs429($this->fxToken($this->fxSuperAdmin()), '/api/v1/auth/change-password', ['new_password' => 'x']);
    }

    /** @test */
    public function kuota_dibagi_antar_route_per_user(): void
    {
        $token = $this->fxToken($this->fxSuperAdmin());

        for ($i = 0; $i < 5; $i++) {
            $this->apiPost('/api/v1/headmasters/999999999/reset-password', $token, ['new_password' => 'PasswordBaru_2026']);
            $this->apiPost('/api/v1/auth/change-password', $token, ['new_password' => 'x']);
        }

        $this->apiPost('/api/v1/auth/change-password', $token, ['new_password' => 'x'])->assertStatus(429);
    }

    /** @test */
    public function user_lain_tidak_terdampak_dan_kunci_bukan_ip(): void
    {
        $sa1 = $this->fxToken($this->fxSuperAdmin('sa_rl_1'));
        $sa2 = $this->fxToken($this->fxSuperAdmin('sa_rl_2'));
        $uri = '/api/v1/headmasters/999999999/reset-password';

        for ($i = 0; $i < 11; $i++) {
            $this->apiPost($uri, $sa1, ['new_password' => 'PasswordBaru_2026']);
        }
        $this->apiPost($uri, $sa1, ['new_password' => 'PasswordBaru_2026'])->assertStatus(429);

        // IP sama (test client), user berbeda: bucket sendiri.
        $this->assertNotSame(429, $this->apiPost($uri, $sa2, ['new_password' => 'PasswordBaru_2026'])->getStatusCode());
    }

    /**
     * Bukti urutan efektif jwt -> role -> limiter: request 401/403 tidak memakai kuota. Regresi yang dicegah:
     * `throttle:` bawaan diurutkan ulang Laravel SEBELUM jwt (kunci jadi `anon`, satu bucket untuk semua user).
     *
     * @test
     */
    public function request_tanpa_token_atau_role_salah_tidak_memakai_kuota(): void
    {
        $uri = '/api/v1/headmasters/999999999/reset-password';

        for ($i = 0; $i < 15; $i++) {
            $this->apiPost($uri, null, ['new_password' => 'PasswordBaru_2026'])->assertStatus(401);
        }

        $school = $this->fxSchool();
        $teacherToken = $this->fxToken($this->fxTeacher($school, '2001'));

        for ($i = 0; $i < 15; $i++) {
            $this->apiPost($uri, $teacherToken, ['new_password' => 'PasswordBaru_2026'])->assertStatus(403);
        }
    }
}
