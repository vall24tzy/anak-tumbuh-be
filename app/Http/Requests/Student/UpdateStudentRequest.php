<?php

namespace App\Http\Requests\Student;

use App\Exceptions\ApiException;
use Illuminate\Foundation\Http\FormRequest;

// API Contract v1.2 §17 — hanya field administratif: name, gender, photo.
// Field API bernama "name" (konsisten dgn POST /students), dipetakan ke
// kolom DB "fullname" di StudentService::update(). Key "fullname" pada body
// PATCH DITOLAK 422 (bukan diterima) — NOTE-06 (24 Sep 2026, db-gap-analysis.md).
// Protected fields (nisn, user_id, school_id, poin, level, classroom,
// academic_year, class_assignment — Matrix #11) DITOLAK 422 eksplisit
// (code: protected_field), bukan silent-drop.

class UpdateStudentRequest extends FormRequest
{
    protected array $allowed = ['name', 'gender', 'photo'];

    // Key teknis method-spoofing (POST multipart + `_method=PATCH`), bukan field
    // data (F-46). Aman dilewati: rules() tidak memuatnya, sehingga validated()
    // tidak pernah membawanya ke StudentService::update().
    protected array $ignored = ['_method'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => ['sometimes', 'string', 'max:150'],
            'gender'   => ['sometimes', 'in:laki-laki,perempuan'],
            'photo'    => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $details = [];

        foreach (array_keys($this->all()) as $field) {
            if (in_array($field, $this->ignored, true)) {
                continue;
            }

            if (!in_array($field, $this->allowed, true)) {
                $details[] = [
                    'field'   => $field,
                    'reason'  => 'protected_field',
                    'message' => "{$field} tidak dapat diubah melalui endpoint ini",
                ];
            }
        }

        if ($details !== []) {
            throw new ApiException(
                'protected_field',
                422,
                $details[0]['message'],
                $details,
            );
        }
    }
}
