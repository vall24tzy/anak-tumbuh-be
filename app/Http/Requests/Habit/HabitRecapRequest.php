<?php

namespace App\Http\Requests\Habit;

use Illuminate\Foundation\Http\FormRequest;

// INTEG-REKAP-001 (Matrix #62, Dokumen Keputusan v4.0 amandemen 20 Okt 2026, D-07/D-11).
// GET /habits/recap?rombel_id=&from=&to=
//
// rombel_id SENGAJA tanpa aturan exists:rombels: rombel yang tidak ada dan rombel milik
// sekolah lain harus menghasilkan respons SAMA (404, D-11), sedangkan exists akan
// menghasilkan 422 yang membedakan keduanya. Keberadaan + scope dicek di service.
// Aturan rentang (from <= to, maksimal 31 hari) dicek di HabitRecapService setelah
// default tanggal dihitung; kegagalannya tetap 422 validation_failed.

class HabitRecapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rombel_id' => ['required', 'integer', 'min:1'],
            'from'      => ['sometimes', 'date_format:Y-m-d'],
            'to'        => ['sometimes', 'date_format:Y-m-d'],
        ];
    }
}
