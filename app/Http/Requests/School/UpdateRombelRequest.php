<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRombelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'             => ['sometimes', 'string', 'max:30'],
            'academic_year_id' => ['sometimes', 'integer', 'exists:academic_years,id'],
            // F-20: lihat CreateRombelRequest (batas atas = integer 32-bit kolom DB).
            'grade_level'      => ['sometimes', 'integer', 'min:1', 'max:2147483647'],
        ];
    }
}