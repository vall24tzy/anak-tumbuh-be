<?php

namespace App\Http\Requests\School;

use App\Models\Headmaster;
use Illuminate\Foundation\Http\FormRequest;

class UpdateHeadmasterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $headmasterId = $this->route('id');
        $userId = Headmaster::find($headmasterId)?->user_id;

        return [
            'fullname' => ['sometimes', 'string', 'max:255'],
            'nip'      => ['sometimes', 'string', 'max:50', 'unique:headmaster,nip,' . $headmasterId],
            'gender'   => ['sometimes', 'in:laki-laki,perempuan'],
            'username' => ['sometimes', 'string', 'max:255', 'unique:users,username,' . $userId],
            'email'    => ['sometimes', 'email', 'unique:users,email,' . $userId],
            'photo'    => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}