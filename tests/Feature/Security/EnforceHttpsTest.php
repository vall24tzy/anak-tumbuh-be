<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\EnforceHttps;
use Tests\TestCase;

// SECURITY-001 butir 4 - HTTPS dipaksa bila config('app.force_https') true: http -> 308 ke https,
// respons https membawa HSTS, /up dikecualikan, default (false) tidak mengubah apa pun.
//
// STATUS EKSEKUSI: ditulis di sandbox tanpa PHP; jalankan `php artisan test --filter=EnforceHttpsTest`.

class EnforceHttpsTest extends TestCase
{
    private const URI = '/api/v1/auth/me';

    /** @test */
    public function default_tidak_aktif_tidak_ada_redirect_dan_tidak_ada_hsts(): void
    {
        $this->assertFalse((bool) config('app.force_https'));

        $response = $this->getJson(self::URI);

        $response->assertStatus(401);
        $this->assertNull($response->headers->get('Strict-Transport-Security'));
    }

    /** @test */
    public function http_dialihkan_308_ke_https_dengan_path_dan_query_utuh(): void
    {
        config(['app.force_https' => true]);

        $response = $this->get('/api/v1/teachers?page=2&per_page=10');

        $response->assertStatus(308);
        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('https://', $location);
        $this->assertStringEndsWith('/api/v1/teachers?page=2&per_page=10', $location);
    }

    /** @test */
    public function host_header_klien_tidak_dipakai_di_url_redirect(): void
    {
        config(['app.force_https' => true, 'app.url' => 'https://api.example.com']);

        $response = $this->call('GET', self::URI, [], [], [], ['HTTP_HOST' => 'evil.example.net']);

        $response->assertStatus(308);
        $this->assertSame('https://api.example.com' . self::URI, $response->headers->get('Location'));
    }

    /** @test */
    public function request_https_diteruskan_dan_membawa_hsts(): void
    {
        config(['app.force_https' => true]);

        $response = $this->getJson('https://localhost' . self::URI);

        $response->assertStatus(401);
        $this->assertSame(
            'max-age=' . EnforceHttps::HSTS_MAX_AGE,
            $response->headers->get('Strict-Transport-Security')
        );
    }

    /** @test */
    public function health_check_up_dikecualikan_dari_pengalihan(): void
    {
        config(['app.force_https' => true]);

        $this->get('/up')->assertStatus(200);
    }
}
