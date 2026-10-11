<?php

namespace App\Support;

/**
 * SECURITY-001 (CORS dibatasi domain spesifik). Mengubah env CORS_ALLOWED_ORIGINS (dipisah koma)
 * menjadi daftar origin yang valid untuk config/cors.php.
 *
 * Aturan (fail-closed):
 *  - Hanya origin persis `skema://host[:port]` (tanpa path, query, wildcard). Trailing slash dibuang.
 *  - Wildcard (`*`, `*.domain`) dan entri tidak valid DIBUANG, bukan diterima.
 *  - Di production hanya `https://` yang diterima (kecuali localhost tidak pernah diizinkan di production).
 *  - Env kosong: production = [] (tidak ada origin lintas-domain yang diizinkan); lingkungan lain = default
 *    localhost dev (3000-3002), perilaku sebelum SECURITY-001.
 */
class CorsOrigins
{
    public const DEV_DEFAULTS = [
        'http://localhost:3000',
        'http://localhost:3001',
        'http://localhost:3002',
    ];

    /** @return string[] */
    public static function resolve(?string $csv, string $appEnv): array
    {
        $production = $appEnv === 'production';
        $origins = [];

        foreach (explode(',', (string) $csv) as $raw) {
            $origin = rtrim(trim($raw), '/');

            if ($origin === '' || !self::isValidOrigin($origin, $production)) {
                continue;
            }

            $origins[$origin] = $origin;
        }

        if ($origins === [] && !$production) {
            return self::DEV_DEFAULTS;
        }

        return array_values($origins);
    }

    protected static function isValidOrigin(string $origin, bool $production): bool
    {
        if (!preg_match('#^(https?)://([a-z0-9]([a-z0-9-]*[a-z0-9])?)(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*(:\d{1,5})?$#i', $origin, $m)) {
            return false;
        }

        if ($production) {
            if (strtolower($m[1]) !== 'https') {
                return false;
            }

            $host = strtolower(parse_url($origin, PHP_URL_HOST) ?? '');

            if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
                return false;
            }
        }

        return true;
    }
}
