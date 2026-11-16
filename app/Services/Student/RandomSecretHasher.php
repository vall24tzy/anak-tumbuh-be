<?php

namespace App\Services\Student;

use Illuminate\Support\Facades\Hash;

/**
 * DEPLOY-IMPORT-001 (D-36, Opsi 1): hash untuk nilai ACAK berentropi tinggi yang dibuat SISTEM:
 * placeholder token QR saat create/import Student (StudentService) dan token QR hasil regenerate (QrTokenService).
 *
 * Alasan (keputusan Pemilik, 16 Nov 2026): nilai ini bukan password yang bisa ditebak. UUID v4 (122 bit acak) dan
 * Str::random(64) tidak bisa dibruteforce, jadi bcrypt round penuh tidak menambah keamanan nyata; ia hanya
 * memperlambat import massal (PILOT T-06: import siswa 150 baris gagal di Vercel dengan round 12, lulus 5,95 detik
 * dengan round 4). Login QR juga dilindungi HMAC-SHA256 dengan APP_KEY sebelum hash dicek.
 *
 * Helper ini SENGAJA terpisah dari InitialPasswordHasher: docblock kelas itu khusus password awal (NIP/NISN).
 *
 * JANGAN dipakai untuk password apa pun (password awal, password pilihan actor) atau nilai yang bisa ditebak.
 * Round global TIDAK diubah.
 *
 * Token lama (hash round penuh) tetap valid tanpa migrasi: Hash::check membaca cost dari string hash itu sendiri,
 * dan QrToken tidak memakai cast `hashed`, jadi tidak ada verifikasi cost terhadap round konfigurasi.
 * `min()` menjamin cost tidak pernah melebihi round konfigurasi (mis. phpunit.xml: 4).
 */
class RandomSecretHasher
{
    public const ROUNDS = 4;

    public static function make(string $secret): string
    {
        return Hash::make($secret, [
            'rounds' => min(self::ROUNDS, (int) config('hashing.bcrypt.rounds', 12)),
        ]);
    }
}
