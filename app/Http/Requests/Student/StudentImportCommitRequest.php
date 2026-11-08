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
            'file'      => ['required', 'file', 'mimes:xlsx', 'max:4096'], // DEPLOY-LIMIT-001 (D-29): 4 MB, di bawah batas body Vercel 4,5 MB
            'rombel_id' => ['required', 'integer', 'exists:rombels,id'],
        ];
    }
}
