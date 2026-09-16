<?php

namespace App\Http\Requests\Habit;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHabitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $habitId = $this->route('id');

        return [
            'name' => ['sometimes', 'string', 'max:255', 'unique:habits,name,' . $habitId],
        ];
    }
}