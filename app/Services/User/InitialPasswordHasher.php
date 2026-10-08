<?php

namespace App\Services\User;

use Illuminate\Support\Facades\Hash;

/**
 * F-55: hash untuk password AWAL yang dibuat SISTEM (NIP untuk Headmaster/Teacher, NISN untuk Student) saat create/import.
 *
 * Alasan (keputusan FINAL pemilik): password awal sama dengan NIP/NISN sehingga memang dapat ditebak (Dokumen v4.0 §4.2);
 * biaya bcrypt tinggi di titik ini tidak menambah keamanan nyata dan hanya memperlambat import massal (terukur ~0,6 dtk per baris
 * pada BCRYPT_ROUNDS bawaan; 101 baris = 60,9 dtk). Karena itu HANYA password awal memakai round rendah.
 *
 * JANGAN dipakai untuk password PILIHAN ACTOR: change-password Super Admin, reset-password Headmaster/Teacher/Student, dan
 * `admin:reset-password` tetap memakai round konfigurasi penuh (`config('hashing.bcrypt.rounds')`) lewat cast `hashed` User.
 * Round global TIDAK diubah.
 *
 * Catatan teknis: cast `hashed` pada User menerima nilai yang sudah ter-hash selama cost-nya tidak melebihi round konfigurasi
 * (Laravel 11 `verifyConfiguration`), jadi hash dari kelas ini tidak di-hash ulang. `min()` di bawah menjamin syarat itu tetap
 * terpenuhi di lingkungan yang round-nya dikonfigurasi lebih rendah (mis. phpunit.xml: 4).
 */
class InitialPasswordHasher
{
    public const ROUNDS = 4;

    public static function make(string $secret): string
    {
        return Hash::make($secret, [
            'rounds' => min(self::ROUNDS, (int) config('hashing.bcrypt.rounds', 12)),
        ]);
    }
}
