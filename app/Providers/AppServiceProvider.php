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
}
