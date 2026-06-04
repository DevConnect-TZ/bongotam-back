<?php

namespace App\Http\Middleware;

use App\Services\ApiSessionService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiAccessToken
{
    public function handle(Request $request, Closure $next, string $role = 'user'): Response
    {
        $session = app(ApiSessionService::class)->validateAccessToken($request->bearerToken(), $request);

        if (! $session) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $request->attributes->set('api_auth_session', $session);
        $request->setUserResolver(static fn () => $session->user);

        return $next($request);
    }
}
