<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordResetToken extends Model
{
    protected $table = 'password_reset_tokens';

    protected $primaryKey = 'email';

    public $incrementing = false;

    protected $keyType = 'string';

    // Tabel ini cuma punya created_at (dari struktur default Laravel), tidak ada updated_at.
    public $timestamps = false;

    protected $fillable = [
        'email',
        'token',
        'created_at',
    ];
}
