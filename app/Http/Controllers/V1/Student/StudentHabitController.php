<?php

namespace App\Http\Controllers\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\ListHabitEntriesRequest;
use App\Http\Requests\Student\StoreHabitEntryRequest;
use App\Http\Resources\Student\HabitEntryResource;
use App\Services\Student\StudentHabitService;
use App\Traits\ApiResponseTraits;

// API Contract v1.2 §21. POST: Student only (Matrix #32). GET: Teacher (own
// active rombel) & Student (own) — Matrix #33/#35; Headmaster/Super Admin 403
// via middleware role. Tidak ada update()/destroy() — Matrix #34, tidak ada
// PATCH/DELETE untuk siapa pun.

class StudentHabitController extends Controller
{
    use ApiResponseTraits;

    public function __construct(protected StudentHabitService $service) {}

    public function index(ListHabitEntriesRequest $request)
    {
        $paginator = $this->service->listHabitEntries($request->validated());

        return $this->successResponse(
            HabitEntryResource::collection($paginator->getCollection()),
            meta: StudentHabitService::paginationMeta($paginator),
        );
    }

    public function show(int $id)
    {
        return $this->successResponse(new HabitEntryResource($this->service->showHabitEntry($id)));
    }

    public function store(StoreHabitEntryRequest $request, int $habit_id)
    {
        $entry = $this->service->storeHabitEntry($habit_id, $request->validated());

        return $this->successResponse(new HabitEntryResource($entry), code: 201);
    }
}
