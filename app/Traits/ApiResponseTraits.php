<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponseTraits
{
    /**
     * Envelope sukses baru sesuai API Contract v1.2 §8: {"data": ..., "meta": ...}
     *
     * $message DIPERTAHANKAN di signature demi kompatibilitas pemanggil LAMA
     * (StudentController, TeacherController, HabitController, dst — belum
     * di-rework, jangan disentuh dulu). Nilainya TIDAK lagi ditulis ke body
     * response karena envelope baru tidak punya field message untuk sukses.
     * Hapus parameter ini nanti setelah semua pemanggil lama selesai direvisi
     * di task masing-masing.
     */
    public function successResponse($data = null, ?string $message = null, int $code = 200, ?array $meta = null): JsonResponse
    {
        $payload = ['data' => $data];

        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $code);
    }

    /**
     * Envelope error baru sesuai API Contract v1.2 §9: {"error": {code, message, details[]}}
     *
     * $errorCode opsional (snake_case, mis. 'validation_failed'). Pemanggil
     * lama yang cuma kirim (message, httpCode) tetap jalan — code diturunkan
     * otomatis dari HTTP status lewat defaultErrorCode().
     */
    public function errorResponse(string $message = 'Error', int $code = 400, $details = null, ?string $errorCode = null): JsonResponse
    {
        return response()->json([
            'error' => [
                'code'    => $errorCode ?? $this->defaultErrorCode($code),
                'message' => $message,
                'details' => $this->normalizeErrorDetails($details),
            ],
        ], $code);
    }

    private function defaultErrorCode(int $status): string
    {
        return match ($status) {
            400 => 'bad_request',
            401 => 'unauthorized',
            403 => 'forbidden',
            404 => 'not_found',
            409 => 'conflict',
            422 => 'validation_failed',
            default => 'internal_error',
        };
    }

    private function normalizeErrorDetails($details): array
    {
        if ($details === null) {
            return [];
        }

        return is_array($details) ? array_values($details) : [$details];
    }
}