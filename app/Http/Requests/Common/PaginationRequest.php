<?php

namespace App\Http\Requests\Common;

use Illuminate\Foundation\Http\FormRequest;

// F-15 / F-10: validasi query param pagination (API Contract v1.2 §7) untuk 6 endpoint GET list.
// Satu FormRequest bersama (bukan satu per endpoint) supaya aturannya identik dan hanya ada satu tempat
// untuk diubah. Pola sama dengan ListHabitEntriesRequest/RankingRequest: per_page > 100 ditolak 422
// validation_failed, BUKAN dipotong diam-diam (perilaku >100 seragam di semua endpoint list).
// Param lain (filter/sort) tidak didukung dan diabaikan, tidak ditolak.

class PaginationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page'     => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
