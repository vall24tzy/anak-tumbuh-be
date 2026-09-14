<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;

class CreateHeadmasterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // otorisasi role sudah dihandle middleware jwt+role
    }

    public function rules(): array
    {
        return [
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'fullname'  => ['required', 'string', 'max:255'],
            'nip'       => ['required', 'string', 'max:50', 'unique:headmaster,nip'],
            'gender'    => ['required', 'in:laki-laki,perempuan'],
            'username'  => ['required', 'string', 'max:255', 'unique:users,username'],
            'email'     => ['required', 'email', 'unique:users,email'],
            'photo'     => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}