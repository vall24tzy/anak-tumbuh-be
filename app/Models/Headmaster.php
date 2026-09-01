<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Headmaster extends Model
{
    use HasFactory;

    // Nama tabel di ERD singular ("headmaster"), Eloquent defaultnya akan menebak
    // "headmasters" kalau tidak dituliskan eksplisit di sini.
    protected $table = 'headmaster';

    protected $fillable = [
        'user_id',
        'school_id',
        'fullname',
        'nip',
        'gender',
        'photo',
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
