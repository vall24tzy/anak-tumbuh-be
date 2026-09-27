<?php

namespace App\Services\Student;

use App\Models\QrToken;
use App\Models\Student;
use App\Services\School\TeacherScopeService;
use App\Services\User\AuditLogService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

// QR-001 — API Contract v1.2 §10/§17, DB Design v1.5 §5.15. Token signed,
// permanent tapi revocable (regenerate meng-update token_hash baris yang sama
// -> token lama otomatis invalid, tidak ada endpoint revoke terpisah).
// TIDAK ada konsep "expired" berbasis waktu untuk QR token (beda dari JWT).
//
// qr_payload = base64(json{student_id, token}) + "." + HMAC-SHA256(payload,
// APP_KEY). DB hanya menyimpan Hash::make(token) — raw token/qr_payload
// HANYA dikembalikan sekali, saat regenerate.

class QrTokenService
{
    public function __construct(
        protected TeacherScopeService $teacherScope,
        protected AuditLogService $auditLog,
    ) {}

    protected function currentUser()
    {
        return auth('api')->user();
    }

    /**
     * POST /api/v1/qr-tokens/{student_id}/regenerate — Teacher only, own
     * active rombel (menumpang scope reset-password Student, §5.6 & §17
     * instruksi §21). Reuse TeacherScopeService + Student::hasActiveAssignmentInRombel()
     * (GAP #5-scope) langsung — bukan lewat StudentService::studentInTeacherActiveRombel()
     * (protected, beda class), primitives yang sama tetap dipakai ulang.
     */
    public function regenerate(int $studentId): string
    {
        $user = $this->currentUser();
        $student = Student::find($studentId);

        if (!$student) {
            throw new HttpException(404, 'Siswa tidak ditemukan');
        }

        $assignment = $this->teacherScope->activeAssignment($user);

        if (!$assignment || !$student->hasActiveAssignmentInRombel($assignment->rombel_id)) {
            throw new HttpException(403, 'Student ini di luar own active rombel Anda');
        }

        $rawToken = Str::random(64);

        // updateOrCreate: 1 baris per Student (dibuat saat POST /students —
        // STUDENT-001), meng-update baris yang sama, bukan insert baru.
        QrToken::updateOrCreate(
            ['student_id' => $student->id],
            [
                'token_hash'     => Hash::make($rawToken),
                'regenerated_at' => now(),
            ],
        );

        // Non-blocking (DB Design v1.5 §5.16) — regenerate tetap sukses walau
        // audit_logs tidak tersedia/gagal ditulis.
        $this->auditLog->record($user, 'qr_regenerate', $student->user);

        return $this->signPayload($student->id, $rawToken);
    }

    protected function signPayload(int $studentId, string $rawToken): string
    {
        $payload = base64_encode(json_encode([
            'student_id' => $studentId,
            'token'      => $rawToken,
        ]));

        $signature = hash_hmac('sha256', $payload, config('app.key'));

        return "{$payload}.{$signature}";
    }

    /**
     * POST /api/v1/auth/login/student/qr — verifikasi signature (HMAC APP_KEY,
     * hash_equals supaya tahan timing attack) lalu cocokkan token_hash.
     * Return null untuk SEMUA kasus tidak valid (signature salah, payload
     * malformed, student_id tidak dikenal, token tidak cocok/sudah
     * diregenerate) — tidak melempar exception, AuthService yang menentukan
     * response 401 (konsisten pola loginStaff/loginStudentPassword).
     */
    public function verifyAndResolveStudent(string $qrPayload): ?Student
    {
        $parts = explode('.', $qrPayload);

        if (count($parts) !== 2) {
            return null;
        }

        [$payload, $signature] = $parts;
        $expected = hash_hmac('sha256', $payload, config('app.key'));

        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $decoded = json_decode(base64_decode($payload), true);

        if (!is_array($decoded) || !isset($decoded['student_id'], $decoded['token'])) {
            return null;
        }

        $qrToken = QrToken::where('student_id', $decoded['student_id'])->first();

        if (!$qrToken || !Hash::check($decoded['token'], $qrToken->token_hash)) {
            return null;
        }

        return $qrToken->student;
    }
}
