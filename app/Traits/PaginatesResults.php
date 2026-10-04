<?php

namespace App\Traits;

use App\Services\Student\StudentHabitService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * F-15 / F-10: SATU helper pagination bersama untuk 6 endpoint GET list (School, Headmaster, Teacher,
 * Academic Year, Rombel, Teacher Assignment). Konvensi API Contract v1.2 §7: `page` (default 1),
 * `per_page` (default 20, maksimum 100), response `meta` = {page, per_page, total, total_pages}.
 * Tidak ada pagination custom per endpoint.
 *
 * Dua sisi dalam satu trait supaya konvensinya tidak bisa melenceng antar endpoint:
 *  - paginateQuery()     : dipakai *Service::index() (sisi query).
 *  - paginatedResponse() : dipakai *Controller::index() (sisi response). Mensyaratkan controller
 *                          memakai ApiResponseTraits (untuk successResponse()).
 *
 * Meta memakai ulang StudentHabitService::paginationMeta() (dipakai ranking dan habit), bukan pola baru
 * (instruksi Matrix F-15: evaluasi reuse, jangan menduplikasi).
 *
 * Validasi `page`/`per_page` ada di App\Http\Requests\Common\PaginationRequest (satu FormRequest bersama,
 * nilai di luar batas -> 422 validation_failed, sama dengan ranking dan habit). Batas di sini hanya
 * pengaman bila service dipanggil langsung tanpa melewati request.
 */
trait PaginatesResults
{
    public const PAGINATION_DEFAULT_PER_PAGE = 20;
    public const PAGINATION_MAX_PER_PAGE = 100;

    /**
     * Urutan deterministik: tie-breaker primary key ASC selalu ditambahkan paling akhir, jadi halaman
     * tidak tumpang tindih atau melompat antar request. Query yang sudah punya orderBy sendiri tetap
     * dihormati (id hanya jadi pembeda terakhir).
     */
    protected function paginateQuery(Builder $query, array $params = []): LengthAwarePaginator
    {
        $perPage = (int) ($params['per_page'] ?? self::PAGINATION_DEFAULT_PER_PAGE);
        $perPage = max(1, min($perPage, self::PAGINATION_MAX_PER_PAGE));
        $page = max(1, (int) ($params['page'] ?? 1));

        $query->orderBy($query->getModel()->getQualifiedKeyName());

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /** `{"data": [...], "meta": {page, per_page, total, total_pages}}`. Halaman di luar rentang: data kosong, meta tetap valid. */
    protected function paginatedResponse(string $resourceClass, LengthAwarePaginator $paginator, ?string $message = null)
    {
        return $this->successResponse(
            $resourceClass::collection($paginator->getCollection()),
            $message,
            meta: StudentHabitService::paginationMeta($paginator),
        );
    }
}
