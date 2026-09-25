<?php

namespace App\Http\Requests\Student;

use App\Exceptions\ApiException;
use Illuminate\Foundation\Http\FormRequest;

// API Contract v1.2 §21 — POST /habits/{habit_id}/entries (6 habit selain
// Bermasyarakat). indicator_id + initiative wajib (Addendum v1.2.1); note
// opsional max 1000, tanpa minimum. description TIDAK diterima (Dokumen v4.0
// §7) -> 422 field_not_accepted eksplisit, bukan silent-drop.
// Existence indicator dicek di Service (-> unknown_indicator), bukan rule exists,
// supaya error.code konsisten.

class StoreHabitEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'indicator_id' => ['required', 'integer'],
            'initiative'   => ['required', 'in:mandiri,disuruh'],
            'note'         => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    // description TIDAK diterima -> 422 dengan error.code = field_not_accepted
    // (API Contract v1.2 §21), dicek SEBELUM validasi lain supaya code-nya
    // tidak tertimpa validation_failed.
    protected function prepareForValidation(): void
    {
        if ($this->has('description')) {
            throw new ApiException(
                'field_not_accepted',
                422,
                'description tidak diterima',
                [['field' => 'description', 'reason' => 'field_not_accepted', 'message' => 'description tidak diterima']],
            );
        }
    }
}
