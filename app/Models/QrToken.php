<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// DB Design v1.5 §5.15 — kredensial QR Login Student. token_hash menyimpan HASH,
// raw token hanya dikirim sekali ke Student saat generate/regenerate (QR-001).

class QrToken extends Model
{
    use HasFactory;

    protected $table = 'qr_tokens';

    protected $fillable = [
        'student_id',
        'token_hash',
        'regenerated_at',
    ];

    protected function casts(): array
    {
        return [
            'regenerated_at' => 'datetime',
        ];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
