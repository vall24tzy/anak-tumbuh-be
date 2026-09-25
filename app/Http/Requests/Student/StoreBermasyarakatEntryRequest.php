<?php

namespace App\Http\Requests\Student;

use App\Exceptions\ApiException;
use Illuminate\Foundation\Http\FormRequest;

// API Contract v1.2 §22 — POST /bermasyarakat/entries. indicator_ids (native
// array, wajib tidak kosong) + initiative wajib. Endpoint ini tidak punya
// field note pada kontrak. description TIDAK diterima (Dokumen v4.0 §7,
// berlaku semua 7 habit termasuk Bermasyarakat) -> 422 field_not_accepted.

class StoreBermasyarakatEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'indicator_ids'   => ['required', 'array', 'min:1'],
            'indicator_ids.*' => ['integer', 'distinct'],
            'initiative'      => ['required', 'in:mandiri,disuruh'],
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
