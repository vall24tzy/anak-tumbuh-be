<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class School extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'logo_path',
    ];

    public function teachers()
    {
        return $this->hasMany(Teacher::class);
    }

    // Histori lengkap Headmaster (aktif + tidak aktif) — DB Design v1.5 §8:
    // schools -> headmasters bersifat 1:N secara skema, hanya 1 baris status='active'.
    public function headmasters()
    {
        return $this->hasMany(Headmaster::class);
    }

    // Headmaster yang sedang aktif saja (di-scope status='active', ditegakkan juga
    // oleh partial unique index di DB — lihat migration headmasters).
    public function headmaster()
    {
        return $this->hasOne(Headmaster::class)->where('status', 'active');
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }
}
