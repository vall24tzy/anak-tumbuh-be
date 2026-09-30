<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponseTraits;
use Closure;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Tymon\JWTAuth\Http\Middleware\Authenticate;

/**
 * Stage 0-B "Grup S" - satu mekanisme validitas sesi untuk F-14 + F-24 + F-29.
 * Menggantikan alias `jwt` (sebelumnya Tymon Authenticate polos).
 *
 * Urutan cek, semuanya per request, TANPA kolom/tabel baru:
 *   1. Autentikasi Tymon (token ada/valid/belum expired/tidak di-blacklist logout,
 *      user ada)                                                    -> 401
 *   2. F-29: claim `pwd` harus sama dengan sidik jari password user SEKARANG.
 *      Password berubah (change/reset) -> semua token lama user itu mati;
 *      token milik admin pelaku reset tidak terpengaruh. Token lama tanpa
 *      claim `pwd` (terbit sebelum patch ini) ditolak - fail-closed         -> 401
 *   3. F-14/F-24: profil Headmaster/Teacher/Student harus status 'active'
 *      (Super Admin dikecualikan). Pola kode sama dengan login R7           -> 403
 */
class AuthenticateActiveSession extends Authenticate
{
    use ApiResponseTraits;

    public function handle($request, Closure $next)
    {
        $this->authenticate($request);

        $user = auth('api')->user();
        $claim = $this->auth->getPayload()->get('pwd');

        if (!$user || !is_string($claim) || !hash_equals($user->passwordFingerprint(), $claim)) {
            throw new UnauthorizedHttpException('jwt-auth', 'Sesi tidak valid, silakan login ulang');
        }

        if (!$user->isLifecycleActive()) {
            return $this->errorResponse('Akun tidak aktif', 403);
        }

        return $next($request);
    }
}
