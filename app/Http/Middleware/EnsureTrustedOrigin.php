<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTrustedOrigin
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->header('Origin');
        $allowed = array_map(static fn (string $value): string => rtrim($value, '/'), [
            ...config('cors.allowed_origins', []),
            $request->getSchemeAndHttpHost(),
        ]);

        if (($origin !== null && ! in_array(rtrim($origin, '/'), $allowed, true))
            || ($origin === null && $request->header('Sec-Fetch-Site') === 'cross-site')) {
            return response()->json(['message' => 'Untrusted request origin.'], 403);
        }

        return $next($request);
    }
}
