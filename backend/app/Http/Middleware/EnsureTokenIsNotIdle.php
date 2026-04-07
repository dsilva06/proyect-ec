<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureTokenIsNotIdle
{
    public function handle(Request $request, Closure $next): Response
    {
        $timeoutMinutes = (int) config('sanctum.idle_timeout', 30);

        if ($timeoutMinutes <= 0) {
            return $next($request);
        }

        $plainTextToken = $request->bearerToken();

        if (! $plainTextToken) {
            return $next($request);
        }

        $accessToken = PersonalAccessToken::findToken($plainTextToken);

        if (! $accessToken) {
            return $next($request);
        }

        $lastActivityAt = $accessToken->last_used_at ?? $accessToken->created_at;

        if ($lastActivityAt !== null && $lastActivityAt->addMinutes($timeoutMinutes)->isPast()) {
            $accessToken->delete();

            throw new AuthenticationException('Unauthorized');
        }

        return $next($request);
    }
}
