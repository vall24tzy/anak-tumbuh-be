<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherClassAssignment extends Model
{
    protected $fillable = [
        'teacher_id',
        'school_id',
        'academic_year_id',
        'rombel_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
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