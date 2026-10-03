<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;

class CreateSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // otorisasi role sudah dihandle middleware jwt+role
    }

    public function rules(): array
    {
        return [
            'name'    => ['required', 'string', 'max:255', 'unique:schools,name'],
            'address' => ['nullable', 'string'],
            'logo'    => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096'],
        ];
    }
}