<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// DB Design v1.5 §5.12 — entry harian habit Student. [REWORK STUDENTHABIT-001]
// TIDAK ADA description/poin snapshot/is_mandiri. classroom = historical
// snapshot (TIDAK PERNAH di-UPDATE, BUKAN source of truth placement — itu
// tetap student_class_assignments). indicator_id NULL untuk Bermasyarakat
// (pilihannya multi-select di student_habit_indicators).

class StudentHabit extends Model
{
    protected $fillable = [
        'student_id',
        'habit_id',
        'indicator_id',
        'habit_date',
        'note',
        'classroom',
        'initiative',
    ];

    protected function casts(): array
    {
        return [
            'habit_date' => 'date',
        ];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function habit()
    {
        return $this->belongsTo(Habit::class);
    }

    public function indicator()
    {
        return $this->belongsTo(Indicator::class);
    }

    // Khusus Bermasyarakat — multi-select indicator (student_habit_indicators).
    public function selectedIndicators()
    {
        return $this->hasMany(StudentHabitIndicator::class);
    }
}
