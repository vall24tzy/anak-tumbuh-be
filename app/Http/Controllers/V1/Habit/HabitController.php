<?php

namespace App\Http\Controllers\V1\Habit;

use App\Http\Controllers\Controller;
use App\Http\Requests\Habit\CreateHabitRequest;
use App\Http\Requests\Habit\UpdateHabitRequest;
use App\Http\Resources\Habit\HabitResource;
use App\Services\Habit\HabitService;
use App\Traits\ApiResponseTraits;

class HabitController extends Controller
{
    use ApiResponseTraits;

    public function __construct(protected HabitService $service) {}

    public function index()
    {
        $habits = $this->service->index();
        return $this->successResponse(HabitResource::collection($habits), 'Daftar habit berhasil diambil');
    }

    public function store(CreateHabitRequest $request)
    {
        $habit = $this->service->store($request->validated());
        return $this->successResponse(new HabitResource($habit), 'Habit berhasil dibuat');
    }

    public function show(int $id)
    {
        $habit = $this->service->show($id);
        return $this->successResponse(new HabitResource($habit), 'Detail habit berhasil diambil');
    }

    public function update(UpdateHabitRequest $request, int $id)
    {
        $habit = $this->service->update($id, $request->validated());
        return $this->successResponse(new HabitResource($habit), 'Habit berhasil diperbarui');
    }

    public function destroy(int $id)
    {
        $this->service->destroy($id);
        return $this->successResponse(null, 'Habit berhasil dihapus');
    }
}