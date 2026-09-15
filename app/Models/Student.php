<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'school_id',
        'fullname',
        'nisn',
        'gender',
        'photo',
        'classroom',
        'grade_level',
        'poin',
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

    public function studentHabitBackups()
    {
        return $this->hasMany(StudentHabitBackup::class);
    }
}
