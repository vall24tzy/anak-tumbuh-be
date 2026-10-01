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
            // F-13: username = nip, dan users.username UNIQUE lintas role (Super Admin, Headmaster,
            // Teacher, Student/NISN). `bail` supaya satu field hanya melaporkan satu kegagalan
            // (duplikat per-tabel tetap 1 error seperti sebelumnya, tanpa pesan ganda).
            'nip'       => ['bail', 'required', 'string', 'max:30', 'unique:teachers,nip', 'unique:users,username'],
            'gender'    => ['required', 'in:laki-laki,perempuan'],
        ];
    }
}