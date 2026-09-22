<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentHabit extends Model
{
    use HasFactory;

    protected $fillable = [
        'habit_id',
        'student_id',
        'indicator_id',
        'classroom',
        'description',
        'note',
        'inisiative',
    ];

    public function habit()
    {
        return $this->belongsTo(Habit::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function indicator()
    {
        return $this->belongsTo(Indicator::class);
    }

    // Relasi ke StudentHabitBackup dihapus — tabel student_habit_backups di-DROP
    // (MIGRATION-002, DB Design v1.5: entity ini tidak ada di Final Entity List §4).
}
