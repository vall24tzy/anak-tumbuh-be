<?php

namespace App\Http\Requests\School;

use App\Exceptions\ApiException;
use Illuminate\Foundation\Http\FormRequest;

// INTEG-TEACHER-001 (keputusan FINAL, Matrix #66): PATCH /teachers/{id}, Headmaster own school, field `name`
// (kolom teachers.fullname) dan `gender` SAJA. nip, username, school_id, status, password, user_id, id, dst.
// DITOLAK 422 eksplisit (code: protected_field), bukan silent-drop (pola UpdateStudentRequest, Matrix #11).
// NIP tidak boleh diubah di tahap ini: users.username = nip, jadi mengubahnya butuh keputusan terpisah
// (keunikan lintas users.username dan nip, token lama dicabut, audit).
// Aturan name/gender mengikuti CreateTeacherRequest (max:150; laki-laki|perempuan).

class UpdateTeacherRequest extends FormRequest
{
    protected array $allowed = ['name', 'gender'];

    // Key teknis method-spoofing, bukan field data (F-46).
    protected array $ignored = ['_method'];

    public function authorize(): bool
    {
        return true; // otorisasi role dihandle middleware, scoping own-school dihandle Service
    }

    public function rules(): array
    {
        return [
            'name'   => ['sometimes', 'required', 'string', 'max:150'],
            'gender' => ['sometimes', 'required', 'in:laki-laki,perempuan'],
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
            throw new ApiException('protected_field', 422, $details[0]['message'], $details);
        }
    }
}
