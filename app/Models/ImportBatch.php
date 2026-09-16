<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportBatch extends Model
{
    protected $fillable = ['type', 'status', 'total', 'success', 'failed', 'failed_rows', 'created_by'];

    protected $casts = [
        'failed_rows' => 'array',
    ];
}