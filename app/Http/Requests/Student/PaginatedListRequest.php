<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

// GET /students dan GET /student-class-assignments — konvensi list umum
// API Contract v1.2 §7: page (default 1), per_page (default 20, maksimum 100).
// per_page > 100 ditolak 422 validation_failed (bukan dipotong diam-diam),
// sama dengan ListHabitEntriesRequest dan RankingRequest. Sengaja TIDAK ada
// filter, sort, atau order baru: kontrak tidak mewajibkannya (lihat
// docs/addendum/API-Contract-v1.2-Addendum-Pagination-Students.md).

class PaginatedListRequest extends FormRequest
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
        ];
    }
}
