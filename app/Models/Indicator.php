<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Indicator extends Model
{
    use HasFactory;

    protected $table = 'indicator';

    // Tabel ini tidak punya created_at/updated_at sesuai ERD.
    public $timestamps = false;

    protected $fillable = [
        'habit_id',
        'name',
        'poin',
    ];

    public function habit()
    {
        return $this->belongsTo(Habit::class);
    }

    public function studentHabits()
    {
        return $this->hasMany(StudentHabit::class);
    }
}
