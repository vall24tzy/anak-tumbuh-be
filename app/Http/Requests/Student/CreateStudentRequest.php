<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class CreateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'school_id'   => ['required', 'integer', 'exists:schools,id'],
            'fullname'    => ['required', 'string', 'max:255'],
            'nisn'        => ['required', 'string', 'max:20', 'unique:students,nisn'],
            'gender'      => ['required', 'in:laki-laki,perempuan'],
            'classroom'   => ['required', 'string', 'max:50'],
            'grade_level' => ['required', 'integer', 'min:1'],
            'photo'       => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}