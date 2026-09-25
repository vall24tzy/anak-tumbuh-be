<?php

namespace App\Http\Controllers\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\ListHabitEntriesRequest;
use App\Http\Requests\Student\StoreBermasyarakatEntryRequest;
use App\Http\Resources\Student\BermasyarakatEntryResource;
use App\Services\Student\StudentHabitService;
use App\Traits\ApiResponseTraits;

// API Contract v1.2 §22. POST: Student only (Matrix #36). GET: Teacher (own
// active rombel) & Student (own) — pola Monitor/View Habit (Matrix #33).
// API Contract §22 hanya mendefinisikan GET list (tanpa /{id}).

class BermasyarakatController extends Controller
{
    use ApiResponseTraits;

    public function __construct(protected StudentHabitService $service) {}

    public function index(ListHabitEntriesRequest $request)
    {
        $paginator = $this->service->listBermasyarakatEntries($request->validated());

        return $this->successResponse(
            BermasyarakatEntryResource::collection($paginator->getCollection()),
            meta: StudentHabitService::paginationMeta($paginator),
        );
    }

    public function store(StoreBermasyarakatEntryRequest $request)
    {
        $entry = $this->service->storeBermasyarakatEntry($request->validated());

        return $this->successResponse(new BermasyarakatEntryResource($entry), code: 201);
    }
}
