<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// DB Design v1.5 §5.11 — FIXED reference, tanpa CRUD. Rename tabel: indicator -> indicators.
// point SELALU NULL untuk 4 indikator Bermasyarakat (scoring count-based, bukan SUM).
// Tanpa timestamps sesuai ERD.

class Indicator extends Model
{
    use HasFactory;

    protected $table = 'indicators';

    public $timestamps = false;

    protected $fillable = [
        'habit_id',
        'name',
        'point',
        'is_exclusive',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_exclusive' => 'boolean',
        ];
    }

    public function habit()
    {
        return $this->belongsTo(Habit::class);
    }

    public function studentHabits()
    {
        return $this->hasMany(StudentHabit::class);
    }
}
