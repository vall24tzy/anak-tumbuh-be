<?php

namespace App\Traits;

use Closure;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Stage 3 (F-03, F-16, F-18, F-11): fallback balapan untuk invarian yang dijaga unique index DB.
 *
 * Pola yang sama dengan HandlesNipConflict (F-13): validasi/pre-check aplikasi menolak kasus
 * sekuensial dengan pesan yang ramah, tetapi dua request konkuren bisa sama-sama lolos
 * pre-check (check-then-insert) lalu yang kedua menabrak unique index -> tanpa fallback ini
 * jadi 500. Beda dengan HandlesNipConflict: trait ini generik. Pemanggil memberi peta
 * `nama_constraint => pabrik exception`, jadi tiap domain menentukan sendiri bentuk errornya
 * (409 ApiException untuk state-conflict, 422 ValidationException untuk duplikat nama).
 * HandlesNipConflict sengaja TIDAK diubah (F-13 sudah verified).
 *
 * Hanya constraint yang ada di peta yang diterjemahkan; pelanggaran lain dilempar ulang
 * apa adanya supaya tidak salah dilaporkan sebagai konflik yang bukan miliknya.
 *
 * PENTING untuk pemanggil: bungkus operasi tulisnya dengan DB::transaction() DI DALAM
 * $callback. Di PostgreSQL statement yang gagal membuat transaksi luar "aborted"; transaksi
 * bersarang (savepoint) memastikan hanya bagian yang gagal yang di-rollback.
 */
trait ConvertsUniqueViolation
{
    /**
     * @param  array<string, Closure():\Throwable>  $constraintMap  nama constraint/index => pabrik exception
     */
    protected function convertUniqueViolation(Closure $callback, array $constraintMap)
    {
        try {
            return $callback();
        } catch (UniqueConstraintViolationException $e) {
            foreach ($constraintMap as $constraint => $makeException) {
                if (str_contains($e->getMessage(), '"' . $constraint . '"')) {
                    throw $makeException();
                }
            }

            throw $e;
        }
    }
}
