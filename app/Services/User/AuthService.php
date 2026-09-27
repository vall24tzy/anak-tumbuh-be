<?php

namespace App\Services\User;

use App\Http\Resources\User\MeResource;
use App\Http\Resources\User\UserResource;
use App\Models\User;
use App\Traits\ApiResponseTraits;
use App\Services\Student\QrTokenService;
use App\Services\User\AuditLogService;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthService
{
    use ApiResponseTraits;

    public function __construct(
        protected AuditLogService $auditLog,
        protected QrTokenService $qrTokenService,
    ) {}

    /**
     * Login staff: Super Admin (username asli) / Headmaster & Teacher (username = NIP).
     * Tidak ada gate status untuk staff - baseline belum mengatur ini (jangan ditebak).
     */
    public function loginStaff(array $data)
    {
        $user = User::where('username', $data['username'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return $this->errorResponse('Username atau password salah', 401);
        }

        return $this->issueLoginResponse($user);
    }

    /**
     * Login Student pakai NISN sebagai username & password (password awal = NISN,
     * lihat STUDENT-001). Ditolak kalau students.status inactive/graduated.
     */
    public function loginStudentPassword(array $data)
    {
        $user = User::where('username', $data['nisn'])
            ->where('role', 'student')
            ->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return $this->errorResponse('NISN atau password salah', 401);
        }

        $student = $user->student;
        if ($student && in_array($student->status, ['inactive', 'graduated'], true)) {
            return $this->errorResponse('Akun siswa tidak aktif', 403);
        }

        return $this->issueLoginResponse($user);
    }

    /**
     * Login Student via QR (Matrix #51) — tanpa verifikasi password. QR
     * token bersifat permanent, TIDAK ada konsep "expired" berbasis waktu
     * (istilah itu hanya untuk JWT) — hanya invalid kalau signature/hash
     * tidak cocok (revoked via regenerate). Gate status sama seperti
     * loginStudentPassword.
     */
    public function loginStudentQr(array $data)
    {
        $student = $this->qrTokenService->verifyAndResolveStudent($data['qr_payload']);

        if (!$student) {
            return $this->errorResponse('QR token tidak valid', 401);
        }

        if (in_array($student->status, ['inactive', 'graduated'], true)) {
            return $this->errorResponse('Akun siswa tidak aktif', 403);
        }

        return $this->issueLoginResponse($student->user);
    }

    /**
     * Method reusable penerbit JWT — dipakai loginStaff/loginStudentPassword/
     * loginStudentQr (QR-001).
     */
    public function issueLoginResponse(User $user)
    {
        $token = JWTAuth::fromUser($user);

        return $this->successResponse([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => new UserResource($user),
        ]);
    }

    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());

        return $this->successResponse(null);
    }

    /**
     * GET /auth/me — identitas dari token, bukan dari input. Scope: Own data.
     */
    public function me(User $user)
    {
        return $this->successResponse(new MeResource($user));
    }

    /**
     * POST /auth/change-password — Super Admin only, own account (Matrix #47).
     */
    public function changePassword(User $user, array $data)
    {
        $user->update(['password' => $data['new_password']]); // auto-hash via cast 'hashed' (User model)

        $this->auditLog->record($user, 'password_change', $user);

        return $this->successResponse([
            'id'   => $user->id,
            'role' => $user->role,
            'name' => $user->username,
        ]);
    }
}
