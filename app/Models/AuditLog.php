<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false; // append-only, cuma created_at (lihat migration)

    protected $fillable = [
        'actor_user_id',
        'action',
        'target_user_id',
    ];

}