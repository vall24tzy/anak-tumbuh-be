<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// DB Design v1.5 §5.14 — FIXED reference, tanpa CRUD/endpoint write untuk role
// manapun. Tanpa timestamps, PK = level (bukan auto-increment).

class GamificationLevel extends Model
{
    protected $table = 'gamification_levels';

    protected $primaryKey = 'level';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'level',
        'required_poin',
    ];
}
