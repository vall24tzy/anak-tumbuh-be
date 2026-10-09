<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * SECURITY-001 butir 5: menerapkan limiter bernama `password-change` (AppServiceProvider).
 *
 * Kenapa bukan `throttle:password-change` bawaan: middleware `throttle` ada di daftar PRIORITAS Laravel,
 * sehingga diurutkan SEBELUM `jwt`/`role` (yang tidak ada di daftar itu). Saat itu user belum terautentikasi:
 * kunci jatuh ke `anon` (satu bucket untuk semua orang) dan request 401/403 ikut memakai kuota.
 * Kelas ini tidak ada di daftar prioritas, jadi tetap berjalan sesudah `jwt` dan `role` sesuai urutan deklarasi route.
 * Terlampaui = ThrottleRequestsException -> 429 `rate_limit_exceeded` (F-47), sama seperti F-23.
 */
class ThrottlePasswordChange
{
    public const LIMITER = 'password-change';

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Limit $limit */
        $limit = call_user_func(RateLimiter::limiter(self::LIMITER), $request);
        $key = md5(self::LIMITER . $limit->key);

        if (RateLimiter::tooManyAttempts($key, $limit->maxAttempts)) {
            throw new ThrottleRequestsException(
                'Too Many Attempts.',
                null,
                ['Retry-After' => RateLimiter::availableIn($key)]
            );
        }

        RateLimiter::hit($key, $limit->decaySeconds);

        return $next($request);
    }
}
