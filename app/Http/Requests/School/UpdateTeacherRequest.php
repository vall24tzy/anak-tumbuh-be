<?php

namespace App\Http\Requests\School;

use App\Models\Teacher;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $teacherId = $this->route('id');
        $userId = Teacher::find($teacherId)?->user_id;

        return [
            'fullname'  => ['sometimes', 'string', 'max:255'],
            'nip'       => ['sometimes', 'string', 'max:50', 'unique:teachers,nip,' . $teacherId],
            'gender'    => ['sometimes', 'in:laki-laki,perempuan'],
            'classroom' => ['sometimes', 'nullable', 'string', 'max:50'],
            'username'  => ['sometimes', 'string', 'max:255', 'unique:users,username,' . $userId],
            'email'     => ['sometimes', 'email', 'unique:users,email,' . $userId],
            'photo'     => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}