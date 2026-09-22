<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// DB Design v1.5 §5.10 — FIXED reference, tanpa CRUD. Tanpa timestamps sesuai ERD.

class Habit extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'allows_multi_select',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'allows_multi_select' => 'boolean',
        ];
    }

    public function indicators()
    {
        return $this->hasMany(Indicator::class)->orderBy('sort_order');
    }

    public function studentHabits()
    {
        return $this->hasMany(StudentHabit::class);
    }
}
