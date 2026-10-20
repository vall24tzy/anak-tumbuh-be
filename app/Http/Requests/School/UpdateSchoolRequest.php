<?php

namespace App\Http\Requests\School;

use App\Exceptions\ApiException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// INTEG-SCHOOL-001 (D-09 FINAL, Matrix #65): PATCH /schools/{id}, Super Admin saja, field `name` SAJA.
// Field lain (address, logo, id, teachers_count, ...) DITOLAK 422 eksplisit (code: protected_field),
// bukan silent-drop — pola UpdateStudentRequest (Matrix #11). Aturan `name` mengikuti CreateSchoolRequest:
// max:150 (F-09, = schools.name varchar(150)) dan unique (F-11) dengan ignore id sekolah sendiri,
// sehingga mengirim nama yang sama dengan nama sekarang tidak dianggap duplikat.

class UpdateSchoolRequest extends FormRequest
{
    protected array $allowed = ['name'];

    // Key teknis method-spoofing, bukan field data (F-46).
    protected array $ignored = ['_method'];

    public function authorize(): bool
    {
        return true; // otorisasi role sudah dihandle middleware jwt+role
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('schools', 'name')->ignore((int) $this->route('id')),
            ],
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
