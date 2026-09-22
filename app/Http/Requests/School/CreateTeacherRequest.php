<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;

class CreateTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // otorisasi role dihandle middleware, scoping own-school dihandle Service
    }

    public function rules(): array
    {
        return [
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'name'      => ['required', 'string', 'max:150'],
            'nip'       => ['required', 'string', 'max:30', 'unique:teachers,nip'],
            'gender'    => ['required', 'in:laki-laki,perempuan'],
        ];
    }
}