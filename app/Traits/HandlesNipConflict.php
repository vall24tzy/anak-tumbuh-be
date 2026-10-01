<?php

namespace App\Traits;

use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * F-13 (fallback, balapan): validasi `unique:users,username` di request sudah menolak NIP
 * yang bentrok. Trait ini hanya menjaga celah check-then-insert: dua request konkuren
 * dengan NIP sama bisa sama-sama lolos validasi, lalu yang kedua kena unique violation
 * saat insert. Itu diubah jadi 422 `validation_failed` pada field `nip` (bentuk yang sama
 * dengan ValidationException biasa), bukan 500.
 *
 * Hanya pelanggaran constraint yang memang berasal dari NIP yang diterjemahkan.
 * Pelanggaran lain (mis. uq_headmasters_one_active_per_school pada suksesi konkuren)
 * dilempar ulang apa adanya, supaya tidak salah dilaporkan sebagai "nip duplikat".
 *
 * Sengaja dipasang di controller (jalur HTTP), BUKAN di TeacherService::store(): service itu
 * juga rowProcessor Import Guru, dan import sudah punya penanganan QueryException sendiri
 * (perilaku NIP kembar antar-baris = F-35, di luar scope F-13).
 */
trait HandlesNipConflict
{
    /** Nama constraint (konvensi Laravel) yang berarti "NIP/username sudah dipakai". */
    protected array $nipConflictConstraints = [
        'users_username_unique',
        'teachers_nip_unique',
        'headmasters_nip_unique',
    ];

    protected function withNipConflictAsValidation(Closure $callback)
    {
        try {
            return $callback();
        } catch (UniqueConstraintViolationException $e) {
            if (!$this->isNipConflict($e)) {
                throw $e;
            }

            throw ValidationException::withMessages([
                'nip' => [trans('validation.unique', ['attribute' => 'nip'])],
            ]);
        }
    }

    protected function isNipConflict(UniqueConstraintViolationException $e): bool
    {
        foreach ($this->nipConflictConstraints as $constraint) {
            if (str_contains($e->getMessage(), '"' . $constraint . '"')) {
                return true;
            }
        }

        return false;
    }
}
