<?php

namespace App\Http\Controllers\V1\School;

use App\Http\Requests\User\ResetPasswordRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\CreateHeadmasterRequest;
use App\Http\Resources\School\HeadmasterResource;
use App\Services\School\HeadmasterService;
use App\Traits\ApiResponseTraits;
use App\Traits\HandlesNipConflict;

class HeadmasterController extends Controller
{
    use ApiResponseTraits, HandlesNipConflict;

    public function __construct(protected HeadmasterService $service) {}

    public function index()
    {
        $headmasters = $this->service->index();
        return $this->successResponse(HeadmasterResource::collection($headmasters));
    }

    public function store(CreateHeadmasterRequest $request)
    {
        $headmaster = $this->withNipConflictAsValidation(fn () => $this->service->store($request->validated()));
        return $this->successResponse(new HeadmasterResource($headmaster), code: 201);
    }

    public function show(int $id)
    {
        $headmaster = $this->service->show($id);
        return $this->successResponse(new HeadmasterResource($headmaster));
    }

    public function resetPassword(ResetPasswordRequest $request, int $id)
    {
        $headmaster = $this->service->resetPassword($id, $request->validated());
        return $this->successResponse(new HeadmasterResource($headmaster));
    }
}