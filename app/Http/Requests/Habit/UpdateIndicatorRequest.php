<?php

namespace App\Http\Requests\Habit;

use Illuminate\Foundation\Http\FormRequest;

class UpdateIndicatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'habit_id' => ['sometimes', 'integer', 'exists:habits,id'],
            'name'     => ['sometimes', 'string', 'max:255'],
            'poin'     => ['sometimes', 'integer', 'min:0'],
        ];
    }
}