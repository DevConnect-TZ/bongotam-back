<?php

namespace App\Services;

use App\Models\AuthSession;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

class ApiSessionService
{
    public function issueSession(User $user, Request $request): array
    {
        $refreshSecret = Str::random(80);

        $session = AuthSession::create([
            'user_id' => $user->id,
            'refresh_token_hash' => hash('sha256', $refreshSecret),
            'refresh_expires_at' => now()->addMinutes($this->refreshTokenTtlMinutes()),
            'last_used_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->sessionPayload($session->load('user'), $refreshSecret, $request);
    }

    public function refreshSession(Request $request): ?array
    {
        $refreshToken = (string) $request->cookie($this->refreshCookieName(), '');

        if ($refreshToken === '') {
            return null;
        }

        [$sessionId, $secret] = $this->parseRefreshToken($refreshToken);

        if (! $sessionId || ! $secret) {
            return null;
        }

        $session = AuthSession::with('user')->find($sessionId);

        if (! $this->sessionUsable($session, $request, true)) {
            if ($session) {
                $this->revokeSession($session);
            }

            return null;
        }

        if (! hash_equals((string) $session->refresh_token_hash, hash('sha256', $secret))) {
            $this->revokeSession($session);

            return null;
        }

        $newSecret = Str::random(80);

        $session->forceFill([
            'refresh_token_hash' => hash('sha256', $newSecret),
            'refresh_expires_at' => now()->addMinutes($this->refreshTokenTtlMinutes()),
            'last_used_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ])->save();

        return $this->sessionPayload($session, $newSecret, $request);
    }

    public function validateAccessToken(?string $token, Request $request): ?AuthSession
    {
        if (! filled($token)) {
            return null;
        }

        $payload = $this->decodeAccessToken($token);

        if (! is_array($payload)) {
            return null;
        }

        $expiresAt = (int) ($payload['exp'] ?? 0);

        if ($expiresAt <= now()->timestamp) {
            return null;
        }

        $sessionId = (string) ($payload['sid'] ?? '');
        $userId = (string) ($payload['uid'] ?? '');

        if ($sessionId === '' || $userId === '') {
            return null;
        }

        $session = AuthSession::with('user')->find($sessionId);

        if (! $this->sessionUsable($session, $request)) {
            return null;
        }

        if ((string) $session->user_id !== $userId) {
            return null;
        }

        return $session;
    }

    public function revokeSessionFromRequest(Request $request): void
    {
        $refreshToken = (string) $request->cookie($this->refreshCookieName(), '');

        if ($refreshToken !== '') {
            [$sessionId] = $this->parseRefreshToken($refreshToken);

            if ($sessionId) {
                $session = AuthSession::find($sessionId);

                if ($session) {
                    $this->revokeSession($session);

                    return;
                }
            }
        }

        $accessToken = $request->bearerToken();
        $session = $this->validateAccessToken($accessToken, $request);

        if ($session) {
            $this->revokeSession($session);
        }
    }

    public function makeRefreshCookie(string $sessionId, string $refreshSecret, Request $request): Cookie
    {
        return cookie(
            $this->refreshCookieName(),
            $this->buildRefreshToken($sessionId, $refreshSecret),
            $this->refreshTokenTtlMinutes(),
            '/api/auth',
            config('session.domain'),
            $this->cookieShouldBeSecure($request),
            true,
            false,
            (string) (config('session.same_site') ?: 'lax'),
        );
    }

    public function forgetRefreshCookie(Request $request): Cookie
    {
        return cookie()->forget(
            $this->refreshCookieName(),
            '/api/auth',
            config('session.domain'),
        );
    }

    public function accessTokenTtlSeconds(): int
    {
        return max(5, (int) env('API_ACCESS_TOKEN_TTL_SECONDS', 30));
    }

    private function refreshTokenTtlMinutes(): int
    {
        return max(1, (int) env('API_REFRESH_TOKEN_TTL_MINUTES', 10080));
    }

    private function refreshCookieName(): string
    {
        return (string) env('API_REFRESH_COOKIE_NAME', 'connection_refresh_token');
    }

    private function sessionPayload(AuthSession $session, string $refreshSecret, Request $request): array
    {
        $expiresAt = now()->addSeconds($this->accessTokenTtlSeconds());

        return [
            'access_token' => $this->encodeAccessToken($session, $expiresAt),
            'expires_in' => $this->accessTokenTtlSeconds(),
            'expires_at' => $expiresAt->toIso8601String(),
            'refresh_cookie' => $this->makeRefreshCookie($session->id, $refreshSecret, $request),
            'session' => $session,
        ];
    }

    private function encodeAccessToken(AuthSession $session, Carbon $expiresAt): string
    {
        return Crypt::encryptString(json_encode([
            'sid' => $session->id,
            'uid' => (string) $session->user_id,
            'exp' => $expiresAt->timestamp,
        ]));
    }

    private function decodeAccessToken(string $token): ?array
    {
        try {
            $decoded = Crypt::decryptString($token);
        } catch (DecryptException) {
            return null;
        }

        $payload = json_decode($decoded, true);

        return is_array($payload) ? $payload : null;
    }

    private function sessionUsable(?AuthSession $session, Request $request, bool $checkUserAgent = false): bool
    {
        if (! $session || ! $session->user) {
            return false;
        }

        if ($session->revoked_at !== null) {
            return false;
        }

        if ($session->refresh_expires_at === null || $session->refresh_expires_at->isPast()) {
            return false;
        }

        if ($session->user->status !== 'active') {
            return false;
        }

        if (
            $checkUserAgent
            && filled($session->user_agent)
            && filled($request->userAgent())
            && ! hash_equals((string) $session->user_agent, (string) $request->userAgent())
        ) {
            return false;
        }

        return true;
    }

    private function revokeSession(AuthSession $session): void
    {
        if ($session->revoked_at !== null) {
            return;
        }

        $session->forceFill([
            'revoked_at' => now(),
        ])->save();
    }

    private function buildRefreshToken(string $sessionId, string $secret): string
    {
        return $sessionId.'.'.$secret;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function parseRefreshToken(string $refreshToken): array
    {
        $parts = explode('.', $refreshToken, 2);

        if (count($parts) !== 2 || ! filled($parts[0]) || ! filled($parts[1])) {
            return [null, null];
        }

        return [$parts[0], $parts[1]];
    }

    private function cookieShouldBeSecure(Request $request): bool
    {
        return $request->isSecure() || str_starts_with((string) config('app.url'), 'https://');
    }
}
