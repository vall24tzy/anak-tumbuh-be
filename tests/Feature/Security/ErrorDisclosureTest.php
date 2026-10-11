<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

// SECURITY-001 butir 1 - APP_DEBUG=false tidak boleh membocorkan detail exception.
//
// Celah sebelumnya: handler Throwable di bootstrap/app.php hanya menyembunyikan pesan bila
// APP_ENV=production; di env lain (staging, local) APP_DEBUG=false tetap mengirim $e->getMessage()
// (bisa berisi SQL, path, nama kolom). Kini dikendalikan config('app.debug').
//
// STATUS EKSEKUSI: ditulis di sandbox tanpa PHP; jalankan `php artisan test --filter=ErrorDisclosureTest`.

class ErrorDisclosureTest extends TestCase
{
    private const SECRET = 'SQLSTATE[XX000]: kolom_rahasia di /var/www/app/Secret.php';

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/api/__security_boom', function () {
            throw new \RuntimeException(self::SECRET);
        });
    }

    /** @test */
    public function debug_false_menyembunyikan_pesan_exception_dan_tidak_ada_trace(): void
    {
        config(['app.debug' => false]);

        $response = $this->getJson('/api/__security_boom');

        $response->assertStatus(500)
            ->assertJsonPath('error.message', 'Terjadi kesalahan pada server');

        $body = $response->getContent();
        $this->assertStringNotContainsString('kolom_rahasia', $body);
        $this->assertStringNotContainsString('Secret.php', $body);
        $this->assertStringNotContainsString('RuntimeException', $body);
        $this->assertStringNotContainsString('"trace"', $body);
        $this->assertStringNotContainsString('vendor/', $body);
    }

    /** @test */
    public function debug_false_menyembunyikan_pesan_juga_di_env_non_production(): void
    {
        // Env test = "testing" (bukan production): perilaku lama membocorkan pesan di sini.
        $this->assertNotSame('production', app()->environment());
        config(['app.debug' => false]);

        $this->getJson('/api/__security_boom')
            ->assertStatus(500)
            ->assertJsonPath('error.code', 'internal_error')
            ->assertJsonPath('error.message', 'Terjadi kesalahan pada server');
    }

    /** @test */
    public function debug_true_tetap_menampilkan_pesan_untuk_pengembangan_lokal(): void
    {
        config(['app.debug' => true]);

        $this->getJson('/api/__security_boom')
            ->assertStatus(500)
            ->assertJsonPath('error.message', self::SECRET);
    }

    /** @test */
    public function default_config_debug_adalah_false_bila_env_tidak_diset(): void
    {
        $config = file_get_contents(config_path('app.php'));

        $this->assertMatchesRegularExpression("/'debug'\s*=>\s*\(bool\)\s*env\('APP_DEBUG',\s*false\)/", $config);
    }
}
