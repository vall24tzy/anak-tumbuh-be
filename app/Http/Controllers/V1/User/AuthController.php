<?php

namespace App\Http\Controllers\V1\User;

use App\Http\Requests\User\ChangePasswordRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\StaffLoginRequest;
use App\Http\Requests\User\StudentPasswordLoginRequest;
use App\Services\User\AuthService;

class AuthController extends Controller
{
    public function __construct(private AuthService $authService)
    {
    }

    public function loginStaff(StaffLoginRequest $request)
    {
        return $this->authService->loginStaff($request->only('username', 'password'));
    }

    public function loginStudentPassword(StudentPasswordLoginRequest $request)
    {
        return $this->authService->loginStudentPassword($request->only('nisn', 'password'));
    }

    public function logout()
    {
        return $this->authService->logout();
    }

    public function me()
    {
        return $this->authService->me(auth('api')->user());
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        return $this->authService->changePassword(auth('api')->user(), $request->validated());
    }
}