<?php

namespace App\Services\User;

use App\Http\Resources\User\UserResource;
use App\Models\PasswordResetToken;
use App\Models\User;
use App\Traits\ApiResponseTraits;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthService
{
    use ApiResponseTraits;

    /**
     * Login. Beda dari kegiatanku-be: di sini juga dicek users.is_active,
     * supaya akun yang dinonaktifkan (bukan dihapus, sesuai keputusan preservasi
     * histori di Requirement v2.1 §3) tidak bisa login.
     */
    public function authSignin(array $data)
    {
        $user = User::where('username', $data['username'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return $this->errorResponse('Username atau password salah', 400);
        }

        if ((int) $user->is_active !== 1) {
            return $this->errorResponse('Akun tidak aktif, hubungi admin sekolah', 403);
        }

        $token = JWTAuth::attempt($data);
        if (!$token) {
            return $this->errorResponse('Username atau password salah', 400);
        }

        // Simpan token yang lagi aktif ke kolom access_token (dipakai nanti untuk
        // kebutuhan revoke/force-logout, sesuai keputusan tim - lihat catatan di signout()).
        $user->access_token = $token;
        $user->save();

        return $this->successResponse([
            'user' => new UserResource($user),
            'access_token' => $token,
        ], 'Berhasil masuk', 200);
    }

    public function authSignout()
    {
        $token = JWTAuth::getToken();
        if (!$token) {
            return $this->errorResponse('Token tidak ditemukan', 401);
        }

        // Ambil user dari token SEBELUM di-invalidate, buat bersihin kolom access_token.
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (JWTException $e) {
            $user = null;
        }

        JWTAuth::invalidate($token);

        if ($user) {
            $user->access_token = null;
            $user->save();
        }

        return $this->successResponse(null, 'Berhasil keluar', 200);
    }

    public function refreshToken()
    {
        try {
            $newToken = JWTAuth::parseToken()->refresh();
        } catch (JWTException $e) {
            return $this->errorResponse('Gagal refresh token', 500);
        }

        // Update juga kolom access_token biar konsisten sama token yang lagi aktif.
        try {
            $user = JWTAuth::setToken($newToken)->toUser();
            if ($user) {
                $user->access_token = $newToken;
                $user->save();
            }
        } catch (JWTException $e) {
            // Kalau gagal ambil user dari token baru, token refresh-nya tetap
            // dikembalikan - ini cuma soal sinkronisasi kolom access_token, bukan
            // syarat mutlak refresh berhasil.
        }

        return $this->successResponse([
            'access_token' => $newToken,
        ], 'Berhasil refresh token', 200);
    }

    /**
     * Beda dari kegiatanku-be: TIDAK benar-benar kirim email dulu (SMTP/Mail
     * belum di-setup di scope task ini). Token dikembalikan langsung di response
     * supaya bisa dites manual. Gampang disambungin ke Mail::send() nanti tinggal
     * uncomment bagian yang ditandai TODO.
     */
    public function forgotPassword(array $data)
    {
        $token = Str::random(60);

        PasswordResetToken::updateOrCreate(
            ['email' => $data['email']],
            ['token' => $token, 'created_at' => now()]
        );

        // TODO (di luar scope AUTH-001): ganti balikin token di response dengan
        // beneran kirim email begitu Mail/SMTP sudah di-setup, misal:
        // Mail::to($data['email'])->send(new ResetPasswordMail($token));

        return $this->successResponse([
            'reset_token' => $token,
        ], 'Token reset password dibuat', 200);
    }

    public function resetPassword(array $data)
    {
        $resetToken = PasswordResetToken::where('token', $data['token'])
            ->where('email', $data['email'])
            ->first();

        if (!$resetToken) {
            return $this->errorResponse('Token reset password tidak valid', 404);
        }

        $user = User::where('email', $data['email'])->first();
        if (!$user) {
            return $this->errorResponse('User tidak ditemukan', 404);
        }

        $user->password = Hash::make($data['password']);
        $user->save();

        PasswordResetToken::where('email', $data['email'])->delete();

        return $this->successResponse(null, 'Berhasil reset password', 200);
    }
}
