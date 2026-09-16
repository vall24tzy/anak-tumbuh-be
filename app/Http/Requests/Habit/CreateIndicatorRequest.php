<?php

namespace App\Http\Requests\Habit;

use Illuminate\Foundation\Http\FormRequest;

class CreateIndicatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role dihandle middleware route (role:1 = Super Admin saja)
    }

    public function rules(): array
    {
        return [
            'habit_id' => ['required', 'integer', 'exists:habits,id'],
            'name'     => ['required', 'string', 'max:255'],
            'poin'     => ['required', 'integer', 'min:0'],
        ];
    }
}