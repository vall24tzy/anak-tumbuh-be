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
            'grade_level'      => ['sometimes', 'integer', 'min:1'],
        ];
    }
}