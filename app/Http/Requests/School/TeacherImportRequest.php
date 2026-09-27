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
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'], // 5120 KB = 5 MB, OD-12
        ];
    }
}