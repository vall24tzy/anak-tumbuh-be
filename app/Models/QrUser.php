<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QrUser extends Model
{
    use HasFactory;

    protected $table = 'qr_user';

    // Tabel ini PUNYA created_at/updated_at (beda dari qr_auth versi lama
    // yang tidak punya) — jadi tidak perlu override $timestamps = false.

    protected $fillable = [
        'user_id',
        'username',
        'password',
        'link_qr',
    ];

    protected $hidden = [
        'password',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
