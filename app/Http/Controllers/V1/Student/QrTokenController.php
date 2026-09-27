<?php

namespace App\Http\Controllers\V1\Student;

use App\Http\Controllers\Controller;
use App\Services\Student\QrTokenService;
use App\Traits\ApiResponseTraits;

// API Contract v1.2 §10/§17. Teacher only, own active rombel. TIDAK ADA
// endpoint generate/revoke terpisah untuk role lain (Matrix, DB Design v1.5
// §5.15) — regenerate adalah satu-satunya mekanisme.

class QrTokenController extends Controller
{
    use ApiResponseTraits;

    public function __construct(protected QrTokenService $service) {}

    public function regenerate(int $studentId)
    {
        $qrPayload = $this->service->regenerate($studentId);

        // qr_payload HANYA dikembalikan di sini, sekali, tidak pernah
        // disimpan plaintext (DB Design v1.5 §5.15).
        return $this->successResponse(['qr_payload' => $qrPayload]);
    }
}
