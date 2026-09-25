<?php

namespace App\Http\Requests\Student;

use App\Services\Student\StudentHabitService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// GET /habits/entries dan GET /bermasyarakat/entries — HANYA konvensi list
// umum API Contract v1.2 §7: page (default 1), per_page (default 20, max 100),
// sort + order. TIDAK ada filter date/student_id/rombel_id (Keputusan Tim
// 24 Sep 2026). Param lain di luar daftar ini tidak dipakai.

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
        ];
    }
}
