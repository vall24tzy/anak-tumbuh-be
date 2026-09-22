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

    public function getJWTCustomClaims(): array
    {
        return [];
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
