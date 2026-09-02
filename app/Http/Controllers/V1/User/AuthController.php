<?php

namespace App\Http\Controllers\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\ForgotPasswordRequest;
use App\Http\Requests\User\ResetPasswordRequest;
use App\Http\Requests\User\SigninRequest;
use App\Services\User\AuthService;

class AuthController extends Controller
{
    public function __construct(private AuthService $authService)
    {
    }

    public function signin(SigninRequest $request)
    {
        return $this->authService->authSignin($request->only('username', 'password'));
    }

    public function signout()
    {
        return $this->authService->authSignout();
    }

    public function refresh()
    {
        return $this->authService->refreshToken();
    }

    public function forgotPassword(ForgotPasswordRequest $request)
    {
        return $this->authService->forgotPassword($request->only('email'));
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        return $this->authService->resetPassword($request->only('token', 'email', 'password'));
    }
}
