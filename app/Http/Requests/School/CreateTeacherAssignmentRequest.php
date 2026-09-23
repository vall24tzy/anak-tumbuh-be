<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;

class CreateTeacherAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'teacher_id'       => ['required', 'integer', 'exists:teachers,id'],
            'school_id'        => ['required', 'integer', 'exists:schools,id'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'rombel_id'        => ['required', 'integer', 'exists:rombels,id'],
        ];
    }
}