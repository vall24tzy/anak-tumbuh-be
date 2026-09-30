<?php

namespace App\Http\Requests\Gamification;

use Illuminate\Foundation\Http\FormRequest;

// API Contract v1.2 §24 (rombel_id, academic_year_id) + §7 (pagination,
// identik ListHabitEntriesRequest — KEPUTUSAN TIM revisi 3). Param lain (mis.
// student_id/sort/order) sengaja TIDAK divalidasi di sini — nilainya diabaikan
// karena Service tidak pernah membacanya (bukan ditolak 422, murni tidak dipakai).

class RankingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rombel_id'        => ['nullable', 'integer', 'min:1'],
            'academic_year_id' => ['nullable', 'integer', 'min:1'],
            'page'             => ['sometimes', 'integer', 'min:1'],
            'per_page'         => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
