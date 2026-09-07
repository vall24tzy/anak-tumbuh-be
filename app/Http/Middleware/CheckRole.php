<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Traits\ApiResponseTraits;

class CheckRole
{
    use ApiResponseTraits;

    public function handle(Request $request, Closure $next, ...$allowedLevels)
    {
        $user = auth('api')->user();

        if (!$user || !in_array((string) $user->level, $allowedLevels)) {
            return $this->errorResponse('Anda tidak memiliki akses ke resource ini', 403);
        }

        return $next($request);
    }
}