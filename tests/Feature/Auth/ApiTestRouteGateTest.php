<?php

namespace Tests\Feature\Auth;

use App\Models\Headmaster;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Concerns\CreatesSchoolFixtures;
use Tests\TestCase;

// STAGE 0-B RESIDUAL F-14 - GET /api/test tidak boleh menjadi jalur bypass.
//
// Akar masalah: route memakai `auth:api` (guard JWT polos): hanya memeriksa token
// valid + user ada + tidak di-blacklist. Tidak memeriksa status lifecycle
// (F-14/F-24) maupun sidik jari password (F-29), jadi token Headmaster inactive
// atau token lama pasca reset password tetap mendapat 200. Bahwa respons hanya
// "ok" tidak membuatnya aman: route ini tetap bukti autentikasi berlaku dan
// jalur pemakaian authority di luar middleware standar.
// Fix: route memakai alias `jwt` (AuthenticateActiveSession) seperti route lain.
//
// Route ini tidak ada di API Contract (endpoint teknis), jadi TIDAK dihapus dan
// responsnya tidak diubah (string 'ok'); hanya gerbangnya yang disamakan.
//
// STATUS EKSEKUSI: belum dijalankan (tidak ada PHP/Postgres di lingkungan
// penyusun patch). Jalankan: php artisan test --filter=ApiTestRouteGateTest

class ApiTestRouteGateTest extends TestCase
{
    use RefreshDatabase, CreatesSchoolFixtures;

    private const URI = '/api/test';

    protected function assertRouteOk($response): void
    {
        $response->assertStatus(200);
        $this->assertSame('ok', $response->getContent());
    }

    /** @test */
    public function tanpa_token_dan_token_invalid_401(): void
    {
        $this->apiGet(self::URI)
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthorized');

        $this->apiGet(self::URI, 'bukan.token.valid')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthorized');
    }

    /** @test */
    public function user_aktif_keempat_role_tetap_200_ok(): void
    {
        $school = $this->fxSchool();

        foreach ([
            $this->fxSuperAdmin('sa_test_route'),
            $this->fxHeadmaster($school, '1001'),
            $this->fxTeacher($school, '2001'),
            $this->fxStudent($school, '0051110001'),
        ] as $user) {
            $this->assertRouteOk($this->apiGet(self::URI, $this->fxToken($user)));
        }
    }

    /** @test */
    public function token_lama_headmaster_teacher_student_inactive_ditolak_403(): void
    {
        $school = $this->fxSchool();
        $hm = $this->fxHeadmaster($school, '1001');
        $teacher = $this->fxTeacher($school, '2001');
        $studentGrad = $this->fxStudent($school, '0051110001');
        $studentInactive = $this->fxStudent($school, '0051110002');

        $tokens = [];
        foreach ([$hm, $teacher, $studentGrad, $studentInactive] as $user) {
            $tokens[$user->id] = $this->fxToken($user);
            $this->assertRouteOk($this->apiGet(self::URI, $tokens[$user->id])); // aktif dulu
        }

        Headmaster::where('user_id', $hm->id)->update(['status' => 'inactive']);
        Teacher::where('user_id', $teacher->id)->update(['status' => 'inactive']);
        Student::where('user_id', $studentGrad->id)->update(['status' => 'graduated']);
        Student::where('user_id', $studentInactive->id)->update(['status' => 'inactive']);

        foreach ($tokens as $token) {
            $this->apiGet(self::URI, $token)
                ->assertStatus(403)
                ->assertJsonPath('error.code', 'forbidden')
                ->assertJsonPath('error.message', 'Akun tidak aktif');
        }
    }

    /** @test */
    public function token_lama_setelah_password_berubah_ditolak_401(): void
    {
        $sa = $this->fxSuperAdmin('sa_test_route');
        $token = $this->fxToken($sa);
        $this->assertRouteOk($this->apiGet(self::URI, $token));

        $sa->update(['password' => 'PasswordBaru123']);

        $this->apiGet(self::URI, $token)
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthorized');
    }

    /** @test */
    public function token_setelah_logout_ditolak_401(): void
    {
        $token = $this->fxToken($this->fxSuperAdmin('sa_test_route'));

        $this->apiPost('/api/v1/auth/logout', $token)->assertStatus(200);

        $this->apiGet(self::URI, $token)->assertStatus(401);
    }

    /** @test */
    public function route_test_memakai_middleware_jwt_bukan_auth_api(): void
    {
        $route = app('router')->getRoutes()->match(Request::create(self::URI, 'GET'));
        $middleware = $route->middleware(); // deklarasi route+group, tanpa menginstansiasi controller

        $this->assertContains('jwt', $middleware);
        $this->assertNotContains('auth:api', $middleware);
    }

    /** @test */
    public function tidak_ada_route_api_yang_memakai_auth_guard_mentah(): void
    {
        $offenders = [];

        foreach (app('router')->getRoutes()->getRoutes() as $route) {
            if (!str_starts_with($route->uri(), 'api/')) {
                continue;
            }

            foreach ($route->middleware() as $middleware) {
                if (is_string($middleware) && ($middleware === 'auth' || str_starts_with($middleware, 'auth:'))) {
                    $offenders[] = $route->uri() . ' -> ' . $middleware;
                }
            }
        }

        $this->assertSame([], $offenders, 'route API harus memakai alias `jwt`, bukan `auth`/`auth:api` mentah (bypass F-14/F-29)');
    }
}
