<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Nama tabel PLURAL "headmasters" (DB Design v1.5 §5.3) — koreksi dari skema
// legacy yang singular. Default Eloquent snake_case-plural sudah cocok,
// tapi tetap dituliskan eksplisit untuk kejelasan.

class Headmaster extends Model
{
    use HasFactory;

    protected $table = 'headmasters';

    protected $fillable = [
        'user_id',
        'school_id',
        'fullname',
        'nip',
        'gender',
        'photo_path',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
