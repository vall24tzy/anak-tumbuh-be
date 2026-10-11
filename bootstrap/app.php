<?php

use App\Exceptions\ApiException;
use App\Traits\ApiResponseTraits;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // SECURITY-001 (HTTPS): hanya aktif bila TRUSTED_PROXIES diisi. Di belakang reverse proxy/CDN,
        // tanpa ini Laravel melihat semua request sebagai http dan EnforceHttps akan mengalihkan tanpa henti.
        $trustedProxies = env('TRUSTED_PROXIES');
        if (is_string($trustedProxies) && trim($trustedProxies) !== '') {
            $middleware->trustProxies(
                at: trim($trustedProxies) === '*' ? '*' : array_map('trim', explode(',', $trustedProxies))
            );
        }

        // SECURITY-001: paksa HTTPS + HSTS (aktif hanya bila config('app.force_https') = true).
        $middleware->append(\App\Http\Middleware\EnforceHttps::class);

        $middleware->alias([
            'throttle.password' => \App\Http\Middleware\ThrottlePasswordChange::class,
            // Stage 0-B (F-14/F-24/F-29): Authenticate bawaan + cek sidik jari password + gate lifecycle.
            'jwt' => \App\Http\Middleware\AuthenticateActiveSession::class,
            'role' => \App\Http\Middleware\CheckRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(function ($request, $throwable) {
            return $request->is('api/*') || $request->expectsJson();
        });

        // Satu "responder" yang pakai ApiResponseTraits, supaya SEMUA render()
        // di bawah bentuk response-nya lewat trait yang sama dengan
        // Controller/Service (hasil SETUP-001 rework) - bukan JSON manual lagi.
        $responder = new class {
            use ApiResponseTraits;
        };

        // Urutan register PENTING: dari yang paling spesifik ke paling umum.
        // Laravel cek satu-satu sesuai urutan ini, berhenti di yang pertama cocok.

        $exceptions->render(function (ModelNotFoundException $e, $request) use ($responder) {
            if ($request->is('api/*')) {
                return $responder->errorResponse('Data tidak ditemukan', 404, [], 'not_found');
            }
        });

        $exceptions->render(function (ValidationException $e, $request) use ($responder) {
            if ($request->is('api/*')) {
                $details = [];
                foreach ($e->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $details[] = [
                            'field'   => $field,
                            'reason'  => 'validation_failed',
                            'message' => $message,
                        ];
                    }
                }

                return $responder->errorResponse(
                    collect($e->errors())->flatten()->first(),
                    422,
                    $details,
                    'validation_failed'
                );
            }
        });

        $exceptions->render(function (ApiException $e, $request) use ($responder) {
            if ($request->is('api/*')) {
                return $responder->errorResponse($e->getMessage(), $e->status(), $e->details(), $e->errorCode());
            }
        });

        $exceptions->render(function (HttpExceptionInterface $e, $request) use ($responder) {
            if ($request->is('api/*')) {
                return $responder->errorResponse(
                    $e->getMessage() ?: 'Terjadi kesalahan',
                    $e->getStatusCode()
                );
            }
        });

        $exceptions->render(function (Throwable $e, $request) use ($responder) {
            if ($request->is('api/*')) {
                return $responder->errorResponse(
                    // SECURITY-001: pesan exception mentah hanya saat APP_DEBUG=true. Sebelumnya hanya
                    // dicek lewat APP_ENV=production, sehingga APP_DEBUG=false di env lain tetap membocorkan pesan.
                    config('app.debug')
                        ? $e->getMessage()
                        : 'Terjadi kesalahan pada server',
                    500
                );
            }
        });
    })->create();