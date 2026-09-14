<?php

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
        $middleware->alias([
            'jwt' => \Tymon\JWTAuth\Http\Middleware\Authenticate::class,
            'role' => \App\Http\Middleware\CheckRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(function ($request, $throwable) {
            return $request->is('api/*') || $request->expectsJson();
        });

        // Urutan register PENTING: dari yang paling spesifik ke paling umum.
        // Laravel cek satu-satu sesuai urutan ini, berhenti di yang pertama cocok.

        $exceptions->render(function (ModelNotFoundException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'code'    => 404,
                    'status'  => 'error',
                    'message' => 'Data tidak ditemukan',
                    'data'    => null,
                ], 404);
            }
        });

        $exceptions->render(function (ValidationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'code'    => 422,
                    'status'  => 'error',
                    'message' => collect($e->errors())->flatten()->first(),
                    'data'    => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (HttpExceptionInterface $e, $request) {
            if ($request->is('api/*')) {
                $status = $e->getStatusCode();
                return response()->json([
                    'code'    => $status,
                    'status'  => 'error',
                    'message' => $e->getMessage() ?: 'Terjadi kesalahan',
                    'data'    => null,
                ], $status);
            }
        });

        $exceptions->render(function (Throwable $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'code'    => 500,
                    'status'  => 'error',
                    'message' => app()->environment('production')
                        ? 'Terjadi kesalahan pada server'
                        : $e->getMessage(),
                    'data'    => null,
                ], 500);
            }
        });
    })->create();