<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

// DB Design v1.5 §5.1 — role menggantikan level; uuid/email/is_active/access_token
// tidak ada di skema final (identity & auth murni: role, username, password).

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'role',
        'username',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    // ----- JWTSubject -----
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * Claim `pwd` = sidik jari password saat token diterbitkan (Stage 0-B, F-29).
     * Middleware `jwt` menolak token yang `pwd`-nya tidak lagi cocok dengan
     * password user sekarang -> change/reset password otomatis mencabut SEMUA
     * token lama user itu, tanpa kolom/tabel baru dan tanpa mengubah pemanggil
     * reset (Headmaster/Teacher/StudentService, AuthService, admin:reset-password).
     */
    public function getJWTCustomClaims(): array
    {
        return ['pwd' => $this->passwordFingerprint()];
    }

    /**
     * HMAC(APP_KEY, hash password) dipotong 128 bit. Bukan hash password mentah,
     * jadi isi payload JWT (yang hanya base64) tidak membocorkan apa pun.
     */
    public function passwordFingerprint(): string
    {
        return substr(
            hash_hmac('sha256', (string) $this->getAuthPassword(), (string) config('app.key')),
            0,
            32
        );
    }

    /**
     * Gate lifecycle (Stage 0-B, F-14 + F-24): true hanya jika akun boleh
     * memegang authority aktif. Super Admin tidak punya profil/lifecycle.
     * Profil Headmaster/Teacher/Student yang hilang diperlakukan tidak aktif
     * (fail-closed). Query dibuat langsung ke DB (bukan relasi ter-cache) supaya
     * perubahan status langsung berlaku di request berikutnya.
     */
    public function isLifecycleActive(): bool
    {
        return match ($this->role) {
            'super_admin' => true,
            'headmaster'  => $this->headmaster()->where('status', 'active')->exists(),
            'teacher'     => $this->teacher()->where('status', 'active')->exists(),
            'student'     => $this->student()->where('status', 'active')->exists(),
            default       => false,
        };
    }

    // ----- Relasi -----
    public function teacher()
    {
        return $this->hasOne(Teacher::class);
    }

    public function headmaster()
    {
        return $this->hasOne(Headmaster::class);
    }

    public function student()
    {
        return $this->hasOne(Student::class);
    }
}
