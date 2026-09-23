<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

// PATCH /student-class-assignments/{id} — Keputusan Tim (db-gap-analysis.md NOTE-05,
// 23 Sep 2026), BUKAN spesifikasi literal dari API Contract v1.2 §19 (yang tidak
// merinci body-nya). Hanya field 'status' (nilai 'ended') yang diterima — TIDAK
// ADA auto-move (dilarang eksplisit oleh §19), field lain ditolak 422 eksplisit
// (code: field_not_accepted), bukan silent-drop.

class UpdateStudentClassAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:ended'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            foreach (array_keys($this->all()) as $field) {
                if ($field !== 'status') {
                    $validator->errors()->add($field, 'field_not_accepted');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'status.required' => 'status wajib diisi',
            'status.in'        => 'status hanya boleh diisi "ended" (tidak ada reactivation)',
        ];
    }
}
