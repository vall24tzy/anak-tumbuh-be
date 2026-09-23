<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rombel extends Model
{
    protected $fillable = [
        'school_id',
        'academic_year_id',
        'name',
        'grade_level',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }
}