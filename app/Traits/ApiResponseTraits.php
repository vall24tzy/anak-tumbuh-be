<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponseTraits
{
    protected function successResponse($data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'code'      => $code,
            'status'    => 'success',
            'message'   => $message,
            'data'      => $data
        ], $code);
    }

    protected function errorResponse(string $message = 'Error', int $code = 400, $data = null): JsonResponse
    {
        return response()->json([
            'code'      => $code,
            'status'    => 'error',
            'message'   => $message,
            'data'      => $data
        ], $code);
    }

}