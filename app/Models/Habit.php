<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Habit extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function indicators()
    {
        return $this->hasMany(Indicator::class);
    }

    public function studentHabits()
    {
        return $this->hasMany(StudentHabit::class);
    }
}
