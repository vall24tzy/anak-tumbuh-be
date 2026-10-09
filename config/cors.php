<?php

use App\Support\CorsOrigins;

// SECURITY-001: origin diambil dari env CORS_ALLOWED_ORIGINS (dipisah koma), bukan daftar localhost
// yang ditanam. Production tanpa env = tidak ada origin lintas-domain yang diizinkan. Aturan lengkap: CorsOrigins.
// Method = yang dipakai routes/v1 (GET/POST/PATCH/DELETE) + preflight; header = yang dibutuhkan klien JSON + JWT.
return [
    'paths' => ['api/*'],
    'allowed_methods'   => ['GET', 'POST', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins'   => CorsOrigins::resolve(env('CORS_ALLOWED_ORIGINS'), (string) env('APP_ENV', 'production')),
    'allowed_origins_patterns'  => [],
    'allowed_headers'   => ['Content-Type', 'Authorization', 'Accept', 'X-Requested-With'],
    'exposed_headers'   => [],
    'max_age'   => 600,
    'supports_credentials'  => false,
];
