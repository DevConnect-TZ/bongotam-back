<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class FirebaseIdentityService
{
    private const DEFAULT_WEB_API_KEY = 'AIzaSyB3L65yGO42EjY_CwuSCUML6iF84l1QDgE';

    public function isConfigured(): bool
    {
        return filled($this->webApiKey());
    }

    public function signInWithEmailAndPassword(string $email, string $password): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::acceptJson()
                ->timeout(20)
                ->post($this->signInUrl(), [
                    'email' => $email,
                    'password' => $password,
                    'returnSecureToken' => true,
                ]);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $identity = $response->json();

        if (! is_array($identity) || ! filled($identity['email'] ?? null)) {
            return null;
        }

        return [
            'uid' => $identity['localId'] ?? null,
            'email' => strtolower((string) $identity['email']),
            'name' => $identity['displayName'] ?? null,
            'id_token' => $identity['idToken'] ?? null,
        ];
    }

    public function lookupByIdToken(string $idToken): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::acceptJson()
                ->timeout(20)
                ->post($this->lookupUrl(), [
                    'idToken' => $idToken,
                ]);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $user = $response->json('users.0');

        if (! is_array($user) || ! filled($user['email'] ?? null)) {
            return null;
        }

        return [
            'uid' => $user['localId'] ?? null,
            'email' => strtolower((string) $user['email']),
            'name' => $user['displayName'] ?? null,
            'email_verified' => (bool) ($user['emailVerified'] ?? false),
        ];
    }

    private function lookupUrl(): string
    {
        return 'https://identitytoolkit.googleapis.com/v1/accounts:lookup?key='
            .urlencode($this->webApiKey());
    }

    private function signInUrl(): string
    {
        return 'https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword?key='
            .urlencode($this->webApiKey());
    }

    private function webApiKey(): string
    {
        $configuredKey = trim((string) config('services.firebase.web_api_key'));

        if ($configuredKey !== '') {
            return $configuredKey;
        }

        // Firebase web API keys are public client identifiers, so the shipped
        // frontend project key is safe to use as a backend lookup fallback.
        return self::DEFAULT_WEB_API_KEY;
    }
}
