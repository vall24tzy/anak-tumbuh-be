<?php

namespace App\Http\Controllers\V1\School;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\CreateRombelRequest;
use App\Http\Requests\School\UpdateRombelRequest;
use App\Http\Resources\School\RombelResource;
use App\Services\School\RombelService;
use App\Traits\ApiResponseTraits;

class RombelController extends Controller
{
    use ApiResponseTraits;

    public function __construct(protected RombelService $service) {}

    public function index()
    {
        return $this->successResponse(RombelResource::collection($this->service->index()));
    }

    public function store(CreateRombelRequest $request)
    {
        $rombel = $this->service->store($request->validated());
        return $this->successResponse(new RombelResource($rombel), code: 201);
    }

    public function show(int $id)
    {
        return $this->successResponse(new RombelResource($this->service->show($id)));
    }

    public function update(UpdateRombelRequest $request, int $id)
    {
        $rombel = $this->service->update($id, $request->validated());
        return $this->successResponse(new RombelResource($rombel));
    }

    public function destroy(int $id)
    {
        $this->service->destroy($id);
        return $this->successResponse(null);
    }
}