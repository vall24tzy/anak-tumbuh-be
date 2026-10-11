<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // SECURITY-001: URL yang dibuat aplikasi (mis. URL foto Student) memakai https bila dipaksa.
        if (config('app.force_https')) {
            URL::forceScheme('https');
        }

        $this->registerLoginIdentifierLimiter();
        $this->registerPasswordChangeLimiter();
    }

    /**
     * F-23 (Matrix v3.1, keputusan FINAL): rate limit login punya DUA counter
     * independen, per IP DAN per identifier, masing-masing 5 percobaan/menit.
     *  - per IP         : `throttle:5,1` bawaan Laravel (kunci domain|IP), tidak diubah.
     *  - per identifier : limiter `login-identifier` di bawah. Identifier = `username`
     *                     (login staff) atau `nisn` (login Student password). Request
     *                     tanpa keduanya (login QR memakai `qr_payload`; body kosong)
     *                     memakai IP sebagai identifier, jadi bucket ini hanya
     *                     menduplikasi counter IP untuk route tersebut.
     * Keduanya dipasang sebagai middleware terpisah; terlampauinya salah satu =
     * 429 `rate_limit_exceeded` (ThrottleRequestsException -> defaultErrorCode F-47).
     * Counter tidak digabung menjadi satu kunci IP+identifier.
     */
    protected function registerLoginIdentifierLimiter(): void
    {
        RateLimiter::for('login-identifier', function (Request $request) {
            foreach (['username' => 'username', 'nisn' => 'nisn'] as $field => $prefix) {
                $value = $request->input($field);

                if ((is_string($value) || is_int($value)) && trim((string) $value) !== '') {
                    return Limit::perMinute(5)->by($prefix . ':' . trim((string) $value));
                }
            }

            return Limit::perMinute(5)->by('ip:' . $request->ip());
        });
    }

    /**
     * SECURITY-001 butir 5 (keputusan pemilik): limiter `password-change` untuk reset-password
     * (headmasters, teachers, students) dan auth/change-password.
     *  - Kunci = ID user penggugah (dari jwt), TANPA IP; kuota 10 request/menit.
     *  - Satu limiter bernama = satu bucket per user yang DIBAGI keempat route (bukan 10 per route).
     *  - Diterapkan oleh middleware `throttle.password` (ThrottlePasswordChange), BUKAN `throttle:` bawaan, supaya berjalan
     *    setelah jwt dan role (lihat penjelasan di kelasnya). Karena itu hanya request yang lolos autentikasi dan role
     *    yang dihitung; request tanpa token (401) atau role salah (403) tidak memakai kuota.
     *  - Terlampaui = 429 `rate_limit_exceeded` (F-47), pola sama dengan F-23. Login tidak diubah.
     */
    protected function registerPasswordChangeLimiter(): void
    {
        RateLimiter::for('password-change', function (Request $request) {
            return Limit::perMinute(10)->by('user:' . (auth('api')->id() ?? 'anon'));
        });
    }
}
