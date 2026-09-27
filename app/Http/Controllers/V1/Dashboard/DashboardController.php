<?php

namespace App\Http\Controllers\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardService;
use App\Traits\ApiResponseTraits;

class DashboardController extends Controller
{
    use ApiResponseTraits;

    public function __construct(protected DashboardService $service) {}

    public function student()
    {
        return $this->successResponse($this->service->forStudent());
    }

    public function teacher()
    {
        return $this->successResponse($this->service->forTeacher());
    }

    public function headmaster()
    {
        return $this->successResponse($this->service->forHeadmaster());
    }

    public function superAdmin()
    {
        return $this->successResponse($this->service->forSuperAdmin());
    }
}