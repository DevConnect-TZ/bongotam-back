<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ApiSessionService;
use App\Services\FirebaseIdentityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class AuthSessionController extends Controller
{
    public function store(
        Request $request,
        FirebaseIdentityService $firebaseIdentity,
        ApiSessionService $apiSessions
    ): JsonResponse {
        $validated = $request->validate([
            'id_token' => 'required|string',
        ]);

        if (! $firebaseIdentity->isConfigured()) {
            return response()->json([
                'message' => 'Firebase authentication is not configured on the server.',
            ], 500);
        }

        $identity = $firebaseIdentity->lookupByIdToken($validated['id_token']);

        if (! $identity || ! filled($identity['email'] ?? null)) {
            return response()->json([
                'message' => 'Could not verify your login session.',
            ], 401);
        }

        $email = strtolower((string) $identity['email']);
        $user = User::firstOrNew([
            'email' => $email,
        ]);

        if (! $user->exists) {
            $user->fill([
                'name' => $identity['name'] ?: Str::before($email, '@'),
                'password' => Str::random(48),
                'role' => 'user',
                'status' => 'active',
            ]);
        } elseif (filled($identity['name'] ?? null) && blank($user->name)) {
            $user->name = (string) $identity['name'];
        }

        if ($user->status === 'banned') {
            return response()->json([
                'message' => 'This account is blocked.',
            ], 403);
        }

        $user->last_login = now();
        $user->save();

        $issued = $apiSessions->issueSession($user, $request);

        return $this->sessionResponse($user, $issued);
    }

    public function refresh(Request $request, ApiSessionService $apiSessions): Response|JsonResponse
    {
        $issued = $apiSessions->refreshSession($request);

        if (! $issued) {
            return response()->json([
                'message' => 'Your session has expired. Please sign in again.',
            ], 401)->withCookie($apiSessions->forgetRefreshCookie($request));
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Session refreshed.',
        ])->withHeaders($this->accessTokenHeaders($issued))
            ->withCookie($issued['refresh_cookie']);
    }

    public function destroy(Request $request, ApiSessionService $apiSessions): JsonResponse
    {
        $apiSessions->revokeSessionFromRequest($request);

        return response()->json([
            'success' => true,
        ])->withCookie($apiSessions->forgetRefreshCookie($request));
    }

    private function sessionResponse(User $user, array $issued): JsonResponse
    {
        return response()->json([
            'access_token' => $issued['access_token'],
            'expires_in' => $issued['expires_in'],
            'expires_at' => $issued['expires_at'],
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'unlocked_connection_videos' => $user->unlocked_connection_videos ?? [],
                'unlocked_wakubwa_videos' => $user->unlocked_wakubwa_videos ?? [],
            ],
        ])
            ->withHeaders($this->accessTokenHeaders($issued))
            ->withCookie($issued['refresh_cookie']);
    }

    private function accessTokenHeaders(array $issued): array
    {
        return [
            'X-Access-Token' => (string) $issued['access_token'],
            'X-Access-Token-Expires-In' => (string) $issued['expires_in'],
            'X-Access-Token-Expires-At' => (string) $issued['expires_at'],
        ];
    }
}
