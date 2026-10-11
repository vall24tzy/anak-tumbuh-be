<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SECURITY-001 (HTTPS dipaksa). Aktif hanya bila `config('app.force_https')` true (env FORCE_HTTPS).
 *  - Request non-HTTPS dialihkan 308 ke URL https yang sama (method dan body dipertahankan oleh klien yang patuh).
 *  - Respons HTTPS membawa `Strict-Transport-Security` (tanpa includeSubDomains/preload: itu keputusan domain).
 *  - `/up` (health check) dikecualikan supaya probe load balancer lewat http tidak gagal.
 * Di belakang proxy/CDN, isi TRUSTED_PROXIES (lihat bootstrap/app.php) agar isSecure() benar.
 */
class EnforceHttps
{
    public const HSTS_MAX_AGE = 15552000; // 180 hari

    public function handle(Request $request, Closure $next): Response
    {
        if (!config('app.force_https') || $request->is('up')) {
            return $next($request);
        }

        if (!$request->isSecure()) {
            // Host diambil dari APP_URL (bukan header Host klien) supaya tidak bisa dipakai sebagai open redirect.
            $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: $request->getHost();

            return redirect()->away('https://' . $host . $request->getRequestUri(), 308);
        }

        $response = $next($request);
        $response->headers->set('Strict-Transport-Security', 'max-age=' . self::HSTS_MAX_AGE);

        return $response;
    }
}
