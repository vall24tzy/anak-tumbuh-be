<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// classroom DIHAPUS — scope Teacher berasal dari teacher_class_assignments
// (TEACHERCLASSASSIGNMENT-001), bukan kolom mentah di sini.

class Teacher extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'school_id',
        'fullname',
        'nip',
        'gender',
        'photo_path',
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
}
