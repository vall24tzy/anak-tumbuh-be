<?php

namespace App\Http\Controllers\V1\School;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\CreateHeadmasterRequest;
use App\Http\Resources\School\HeadmasterResource;
use App\Services\School\HeadmasterService;
use App\Traits\ApiResponseTraits;

class HeadmasterController extends Controller
{
    use ApiResponseTraits;

    public function __construct(protected HeadmasterService $service) {}

    public function index()
    {
        $headmasters = $this->service->index();
        return $this->successResponse(HeadmasterResource::collection($headmasters));
    }

    public function store(CreateHeadmasterRequest $request)
    {
        $headmaster = $this->service->store($request->validated());
        return $this->successResponse(new HeadmasterResource($headmaster), code: 201);
    }

    public function show(int $id)
    {
        $headmaster = $this->service->show($id);
        return $this->successResponse(new HeadmasterResource($headmaster));
    }
}