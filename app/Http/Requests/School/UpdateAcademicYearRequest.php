<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAcademicYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'       => ['sometimes', 'string', 'max:30'],
            'start_date' => ['sometimes', 'date'],
            // F-04: `after:start_date` hanya bermakna bila start_date ikut dikirim. Tanpa itu
            // Laravel tidak punya pembanding dan bisa meloloskan nilai yang melanggar CHECK DB.
            // Perbandingan terhadap NILAI TERSIMPAN dilakukan AcademicYearService::update().
            'end_date'   => ['sometimes', 'date', Rule::when($this->has('start_date'), ['after:start_date'])],
        ];
    }
}