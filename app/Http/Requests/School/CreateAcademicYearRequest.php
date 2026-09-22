<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;

class CreateAcademicYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role dihandle middleware, scoping own-school dihandle Service
    }

    public function rules(): array
    {
        return [
            'name'       => ['required', 'string', 'max:30'],
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after:start_date'],
        ];
    }
}