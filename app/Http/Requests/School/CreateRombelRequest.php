<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;

class CreateRombelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role dihandle middleware, scoping own-school dihandle Service
    }

    public function rules(): array
    {
        return [
            'school_id'        => ['required', 'integer', 'exists:schools,id'],
            'name'             => ['required', 'string', 'max:30'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'grade_level'      => ['required', 'integer', 'min:1'],
        ];
    }
}