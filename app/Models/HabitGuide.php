<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// INTEG-PANDUAN-001 — panduan pengisian kebiasaan per (rombel x habit); teks polos.
// Lihat docs/addendum/DB-Design-v1.5-Errata-Habit-Guides.md.

class HabitGuide extends Model
{
    protected $fillable = [
        'rombel_id',
        'habit_id',
        'content',
    ];

    public function rombel()
    {
        return $this->belongsTo(Rombel::class);
    }

    public function habit()
    {
        return $this->belongsTo(Habit::class);
    }
}
