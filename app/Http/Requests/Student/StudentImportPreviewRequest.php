<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class StudentImportPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // F-33: format FINAL .xlsx saja (API Contract §18, OD-12); .xls ditolak 422.
            'file' => ['required', 'file', 'mimes:xlsx', 'max:4096'], // DEPLOY-LIMIT-001 (D-29): 4096 KB = 4 MB, di bawah batas body Vercel 4,5 MB
        ];
    }
}
