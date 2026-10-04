<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;

class TeacherImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // otorisasi role dihandle middleware
    }

    public function rules(): array
    {
        return [
            // F-33: format FINAL .xlsx saja (API Contract §18, OD-12); .xls ditolak 422.
            'file' => ['required', 'file', 'mimes:xlsx', 'max:5120'], // 5120 KB = 5 MB, OD-12
        ];
    }
}