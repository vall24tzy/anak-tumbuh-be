<?php

namespace App\Http\Controllers\V1\Habit;

use App\Http\Controllers\Controller;
use App\Http\Requests\Habit\CreateIndicatorRequest;
use App\Http\Requests\Habit\UpdateIndicatorRequest;
use App\Http\Resources\Habit\IndicatorResource;
use App\Services\Habit\IndicatorService;
use App\Traits\ApiResponseTraits;

class IndicatorController extends Controller
{
    use ApiResponseTraits;

    public function __construct(protected IndicatorService $service) {}

    public function index()
    {
        $indicators = $this->service->index();
        return $this->successResponse(IndicatorResource::collection($indicators), 'Daftar indicator berhasil diambil');
    }

    public function store(CreateIndicatorRequest $request)
    {
        $indicator = $this->service->store($request->validated());
        return $this->successResponse(new IndicatorResource($indicator), 'Indicator berhasil dibuat');
    }

    public function show(int $id)
    {
        $indicator = $this->service->show($id);
        return $this->successResponse(new IndicatorResource($indicator), 'Detail indicator berhasil diambil');
    }

    public function update(UpdateIndicatorRequest $request, int $id)
    {
        $indicator = $this->service->update($id, $request->validated());
        return $this->successResponse(new IndicatorResource($indicator), 'Indicator berhasil diperbarui');
    }

    public function destroy(int $id)
    {
        $this->service->destroy($id);
        return $this->successResponse(null, 'Indicator berhasil dihapus');
    }
}