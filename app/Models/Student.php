<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// classroom & grade_level DIHAPUS (DB Design v1.5 Risk #1) — digantikan sepenuhnya
// oleh student_class_assignments + rombels.grade_level. level/gamification_level
// tidak disimpan sebagai kolom — dihitung real-time (LEVEL-RANKING-001).

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'school_id',
        'fullname',
        'nisn',
        'gender',
        'photo_path',
        'poin',
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

    public function studentHabits()
    {
        return $this->hasMany(StudentHabit::class);
    }

    // QR token terikat ke Student (bukan User generik) — DB Design v1.5 §5.15.
    public function qrToken()
    {
        return $this->hasOne(QrToken::class);
    }
}
