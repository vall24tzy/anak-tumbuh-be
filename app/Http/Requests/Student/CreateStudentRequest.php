<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

// API Contract v1.2 §17 — Request Body POST /students:
// { "rombel_id": 7, "name": "string", "nisn": "string", "gender": "laki-laki" }
// nisn TIDAK divalidasi 'unique' — NISN existing (sekolah sama) di-reuse (Dokumen
// v4.0 §6.2), reject cross-school ditangani di Service (bukan di sini).

class CreateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rombel_id' => ['required', 'integer', 'exists:rombels,id'],
            'name'      => ['required', 'string', 'max:150'],
            'nisn'      => ['required', 'string', 'max:20'],
            'gender'    => ['required', 'in:laki-laki,perempuan'],
            'photo'     => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
