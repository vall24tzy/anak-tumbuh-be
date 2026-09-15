<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QrUser extends Model
{
    use HasFactory;

    protected $table = 'qr_user';

    protected $fillable = [
        'user_id',
        'token',
        'link_qr',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}