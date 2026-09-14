<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;

class CreateTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // otorisasi role & scoping sekolah dihandle di Service
    }

    public function rules(): array
    {
        return [
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'fullname'  => ['required', 'string', 'max:255'],
            'nip'       => ['required', 'string', 'max:50', 'unique:teachers,nip'],
            'gender'    => ['required', 'in:laki-laki,perempuan'],
            'classroom' => ['nullable', 'string', 'max:50'],
            'username'  => ['required', 'string', 'max:255', 'unique:users,username'],
            'email'     => ['required', 'email', 'unique:users,email'],
            'photo'     => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}