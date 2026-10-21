<?php

namespace App\Http\Requests\Habit;

use Illuminate\Foundation\Http\FormRequest;

// INTEG-PANDUAN-001 (Matrix #63, D-06, D-13) — PATCH /rombels/{rombel_id}/habit-guides/{habit_id}.
//
// content WAJIB ADA sebagai key ('present'): nilainya string (maks 2.000 karakter) atau
// kosong/null yang berarti HAPUS panduan (D-13). Key yang hilang -> 422 supaya request yang
// salah kirim tidak menghapus panduan secara tidak sengaja. String kosong dan string yang
// hanya berisi spasi sampai sebagai null (TrimStrings + ConvertEmptyStringsToNull).
//
// Teks polos: nilai yang memuat tag HTML ditolak (422), bukan dibersihkan diam-diam. Markdown
// tidak diproses: karakter apa pun selain tag HTML disimpan apa adanya dan dikeluarkan sebagai
// string JSON biasa.

class UpdateHabitGuideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => [
                'present',
                'nullable',
                'string',
                'max:2000',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_string($value) && preg_match('/<\/?[A-Za-z!][^>]*>/', $value)) {
                        $fail('content harus teks polos, tidak boleh memuat HTML');
                    }
                },
            ],
        ];
    }
}
