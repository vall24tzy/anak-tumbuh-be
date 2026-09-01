<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'uuid',
        'username',
        'email',
        'password',
        'level',
        'is_active',
        'access_token',
    ];

    protected $hidden = [
        'password',
        'access_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'integer',
            'level' => 'integer',
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

    public function qrUser()
    {
        return $this->hasOne(QrUser::class);
    }
}
