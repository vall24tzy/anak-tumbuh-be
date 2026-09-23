<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

// API Contract v1.2 §19 — Request Body POST /student-class-assignments:
// { "student_id": 55, "rombel_id": 7, "academic_year_id": 3 }
// TIDAK ada school_id di body — school_id derived dari rombels.school_id (Service).

class CreateStudentClassAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id'       => ['required', 'integer', 'exists:students,id'],
            'rombel_id'        => ['required', 'integer', 'exists:rombels,id'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
        ];
    }
}
