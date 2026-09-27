<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

// API Contract v1.2 §10 — Request Body POST /auth/login/student/qr:
// { "qr_payload": "string" }

class StudentQrLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'qr_payload' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'qr_payload.required' => 'qr_payload tidak boleh kosong',
        ];
    }
}
