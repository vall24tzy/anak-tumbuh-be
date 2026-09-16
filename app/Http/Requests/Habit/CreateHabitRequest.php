<?php

namespace App\Http\Requests\Habit;

use Illuminate\Foundation\Http\FormRequest;

class CreateHabitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role dihandle middleware route (role:1 = Super Admin saja)
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:habits,name'],
        ];
    }
}