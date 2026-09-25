<?php

namespace App\Http\Requests\Student;

use App\Services\Student\StudentHabitService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// GET /habits/entries dan GET /bermasyarakat/entries — konvensi list umum
// API Contract v1.2 §7: page (default 1), per_page (default 20, max 100),
// sort + order. Filter opsional `date=YYYY-MM-DD` (Addendum: Habit GET
// Response & Date Filter, Keputusan Tim 25 Sep 2026) — filter HANYA
// berdasarkan habit_date, TIDAK memengaruhi authorization scope. TIDAK ada
// filter student_id/rombel_id (Keputusan Tim 24 & 25 Sep 2026 — client
// parameter tidak boleh menjadi penentu authorization scope).

class ListHabitEntriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page'     => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort'     => ['sometimes', Rule::in(StudentHabitService::SORTABLE)],
            'order'    => ['sometimes', 'in:asc,desc'],
            'date'     => ['sometimes', 'date_format:Y-m-d'],
        ];
    }
}
