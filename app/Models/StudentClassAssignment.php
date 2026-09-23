<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// DB Design v1.5 §5.9 — SATU-SATUNYA source of truth current placement + history
// Student. status: 'active' = current placement, 'ended' = historis.

class StudentClassAssignment extends Model
{
    protected $fillable = [
        'student_id',
        'school_id',
        'rombel_id',
        'academic_year_id',
        'status',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function rombel()
    {
        return $this->belongsTo(Rombel::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
