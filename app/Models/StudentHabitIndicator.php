<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// DB Design v1.5 §5.13 — pivot khusus Bermasyarakat (multi-select).

class StudentHabitIndicator extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'student_habit_id',
        'indicator_id',
    ];

    public function studentHabit()
    {
        return $this->belongsTo(StudentHabit::class);
    }

    public function indicator()
    {
        return $this->belongsTo(Indicator::class);
    }
}
