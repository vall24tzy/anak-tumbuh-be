<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $studentId = $this->route('id');

        return [
            'fullname'    => ['sometimes', 'string', 'max:255'],
            'nisn'        => ['sometimes', 'string', 'max:20', 'unique:students,nisn,' . $studentId],
            'gender'      => ['sometimes', 'in:laki-laki,perempuan'],
            'classroom'   => ['sometimes', 'string', 'max:50'],
            'grade_level' => ['sometimes', 'integer', 'min:1'],
            'photo'       => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}