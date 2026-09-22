<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class StudentPasswordLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nisn'     => 'required|string',
            'password' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'nisn.required'     => 'NISN tidak boleh kosong',
            'password.required' => 'Password tidak boleh kosong',
        ];
    }
}