<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class School extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'logo',
    ];

    public function teachers()
    {
        return $this->hasMany(Teacher::class);
    }

    public function headmaster()
    {
        return $this->hasOne(Headmaster::class);
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }
}
