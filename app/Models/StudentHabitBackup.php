<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentHabitBackup extends Model
{
    use HasFactory;

    // Tabel ini tidak punya created_at/updated_at sesuai ERD.
    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'student_habit_id',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function studentHabit()
    {
        return $this->belongsTo(StudentHabit::class);
    }
}
