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
            'name'      => ['required', 'string', 'max:150'],
            'nip'       => ['required', 'string', 'max:30', 'unique:headmasters,nip'],
            'gender'    => ['required', 'in:laki-laki,perempuan'],
        ];
    }
}