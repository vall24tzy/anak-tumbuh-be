<?php

namespace App\Services\User;

use App\Http\Resources\User\MeResource;
use App\Http\Resources\User\UserResource;
use App\Models\User;
use App\Traits\ApiResponseTraits;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthService
{
    use ApiResponseTraits;

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
     * Method reusable penerbit JWT - dipakai lagi di QR-001 nanti (login QR
     * tanpa verifikasi password, tapi tetap terbitkan token lewat method ini).
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
}