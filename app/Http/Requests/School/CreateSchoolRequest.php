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
            // F-09: max disamakan dengan kolom DB schools.name varchar(150) (dulu 255 -> 151..255 lolos lalu 500).
            // F-11: unique di aplikasi; constraint DB (schools_name_unique) menjaga balapan, lihat SchoolService::store().
            'name'    => ['required', 'string', 'max:150', 'unique:schools,name'],
            'address' => ['nullable', 'string'],
            'logo'    => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096'],
        ];
    }
}