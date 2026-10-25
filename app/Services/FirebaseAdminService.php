<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FirebaseAdminService
{
    public function isConfigured(): bool
    {
        return filled(config('services.firebase.project_id'))
            && filled(config('services.firebase.client_email'))
            && filled(config('services.firebase.private_key'));
    }

    public function deleteUserByEmail(string $email): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Firebase Admin credentials are not configured.');
        }

        $accessToken = $this->accessToken();
        $lookupResponse = Http::acceptJson()
            ->withToken($accessToken)
            ->connectTimeout(5)
            ->timeout(15)
            ->post($this->accountsUrl('lookup'), [
                'email' => [strtolower($email)],
            ]);

        if (! $lookupResponse->successful()) {
            throw new RuntimeException('Firebase user lookup failed.');
        }

        $firebaseUserId = $lookupResponse->json('users.0.localId');

        if (! filled($firebaseUserId)) {
            return;
        }

        $deleteResponse = Http::acceptJson()
            ->withToken($accessToken)
            ->connectTimeout(5)
            ->timeout(15)
            ->post($this->accountsUrl('delete'), [
                'localId' => $firebaseUserId,
            ]);

        if (! $deleteResponse->successful()) {
            throw new RuntimeException('Firebase user deletion failed.');
        }
    }

    private function accessToken(): string
    {
        $cacheKey = 'firebase_admin_access_token:'.hash('sha256', $this->projectId().$this->clientEmail());

        return Cache::remember($cacheKey, now()->addMinutes(50), function (): string {
            $response = Http::asForm()
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(15)
                ->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $this->serviceAccountAssertion(),
                ]);

            if (! $response->successful() || ! filled($response->json('access_token'))) {
                throw new RuntimeException('Could not authenticate the Firebase service account.');
            }

            return (string) $response->json('access_token');
        });
    }

    private function serviceAccountAssertion(): string
    {
        $now = now()->timestamp;
        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ], JSON_THROW_ON_ERROR));
        $claims = $this->base64UrlEncode(json_encode([
            'iss' => $this->clientEmail(),
            'sub' => $this->clientEmail(),
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
            'scope' => 'https://www.googleapis.com/auth/identitytoolkit',
        ], JSON_THROW_ON_ERROR));
        $unsignedToken = $header.'.'.$claims;
        $signature = '';

        if (! openssl_sign($unsignedToken, $signature, $this->privateKey(), OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Could not sign the Firebase service account assertion.');
        }

        return $unsignedToken.'.'.$this->base64UrlEncode($signature);
    }

    private function accountsUrl(string $action): string
    {
        return 'https://identitytoolkit.googleapis.com/v1/projects/'
            .rawurlencode($this->projectId())
            .'/accounts:'
            .$action;
    }

    private function projectId(): string
    {
        return trim((string) config('services.firebase.project_id'));
    }

    private function clientEmail(): string
    {
        return trim((string) config('services.firebase.client_email'));
    }

    private function privateKey(): string
    {
        return str_replace('\\n', "\n", trim((string) config('services.firebase.private_key')));
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
