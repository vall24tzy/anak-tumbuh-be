<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

// API Contract v1.2 §18 — multipart: file .xlsx + rombel_id (request-level,
// sama untuk seluruh baris file).

class StudentImportCommitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // F-33: format FINAL .xlsx saja (API Contract §18, OD-12); .xls ditolak 422.
            'file'      => ['required', 'file', 'mimes:xlsx', 'max:5120'],
            'rombel_id' => ['required', 'integer', 'exists:rombels,id'],
        ];
    }
}
