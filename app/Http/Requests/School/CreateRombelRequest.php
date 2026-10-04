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
            // F-20: batas atas = maksimum kolom integer 32-bit (rombels.grade_level). SoT tidak
            // menetapkan rentang bisnis (hanya CHECK > 0), jadi tidak ada angka bisnis yang dikarang.
            'grade_level'      => ['required', 'integer', 'min:1', 'max:2147483647'],
        ];
    }
}