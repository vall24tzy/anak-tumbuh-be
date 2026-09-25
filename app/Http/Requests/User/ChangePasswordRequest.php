<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role dihandle middleware
    }

    public function rules(): array
    {
        return [
            'new_password' => ['required', 'string', 'min:8'],
        ];
    }
}