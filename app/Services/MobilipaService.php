<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class MobilipaService
{
    public function createOrder(array $payload): array
    {
        try {
            $normalizedPhone = isset($payload['buyer_phone'])
                ? $this->normalizePhoneNumber((string) $payload['buyer_phone'])
                : ($this->normalizePhoneNumber((string) ($payload['phone'] ?? '')));

            $body = array_merge($payload, [
                'buyer_phone' => $normalizedPhone,
                'phone' => $normalizedPhone,
                'msisdn' => $normalizedPhone,
                'buyer_email' => $payload['buyer_email'] ?? $payload['email'] ?? null,
                'buyer_name' => $payload['buyer_name'] ?? $payload['name'] ?? null,
                'amount' => (int) ($payload['amount'] ?? 0),
                'currency' => strtoupper((string) ($payload['currency'] ?? 'TZS')),
            ]);

            $body = array_filter($body, static fn ($value) => $value !== null);

            $response = Http::acceptJson()
                ->timeout(45)
                ->withHeaders($this->getHeaders())
                ->post($this->createOrderUrl(), $body);
        } catch (ConnectionException $exception) {
            return [
                'status' => 'error',
                'message' => 'Unable to connect to Mobilipa. Please try again shortly.',
                'http_status' => 502,
                'data' => [
                    'reason' => $exception->getMessage(),
                ],
            ];
        }

        return $this->decodeResponse($response);
    }

    public function orderStatus(string $orderId): array
    {
        try {
            $headers = $this->getHeaders();

            // First attempt GET with query parameter
            $response = Http::acceptJson()
                ->timeout(45)
                ->withHeaders($headers)
                ->get($this->orderStatusUrl(), [
                    'order_id' => $orderId,
                ]);

            // If GET returns 404 or 405 Method Not Allowed, fallback to POST with JSON body
            if (in_array($response->status(), [404, 405], true)) {
                $response = Http::acceptJson()
                    ->timeout(45)
                    ->withHeaders($headers)
                    ->post($this->orderStatusUrl(), [
                        'order_id' => $orderId,
                    ]);
            }
        } catch (ConnectionException $exception) {
            return [
                'status' => 'error',
                'message' => 'Unable to check Mobilipa order status. Please try again shortly.',
                'http_status' => 502,
                'data' => [
                    'reason' => $exception->getMessage(),
                ],
            ];
        }

        return $this->decodeResponse($response);
    }

    public function isConfigured(): bool
    {
        return filled(config('services.mobilipa.api_key'));
    }

    public function verifyWebhookSignature(string $payloadRaw, ?string $signature): bool
    {
        $secret = config('services.mobilipa.webhook_secret')
            ?? config('services.mobilipa.api_secret')
            ?? config('services.mobilipa.api_key');

        if (! filled($signature) || ! filled($secret)) {
            // When no signature is provided or secret is missing, require configured API key
            return filled(config('services.mobilipa.api_key'));
        }

        $expected = hash_hmac('sha256', $payloadRaw, (string) $secret);

        return hash_equals($expected, (string) $signature);
    }

    public function normalizePhoneNumber(string $phoneNumber): ?string
    {
        $digits = preg_replace('/\D+/', '', $phoneNumber);

        if ($digits === null || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '255') && strlen($digits) === 12) {
            return $digits;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '255'.substr($digits, 1);
        }

        if (in_array(substr($digits, 0, 1), ['6', '7'], true) && strlen($digits) === 9) {
            return '255'.$digits;
        }

        if (strlen($digits) === 9) {
            return '255'.$digits;
        }

        return null;
    }

    public function createOrderUrl(): string
    {
        $baseUrl = rtrim((string) config('services.mobilipa.base_url', 'https://mobilipa.store/api/v1'), '/');

        if (str_ends_with($baseUrl, 'request-payment.php')) {
            return $baseUrl;
        }

        if (str_ends_with($baseUrl, '/api/v1') || str_ends_with($baseUrl, '/v1')) {
            return $baseUrl.'/request-payment.php';
        }

        return $baseUrl.'/api/v1/request-payment.php';
    }

    public function orderStatusUrl(): string
    {
        $baseUrl = rtrim((string) config('services.mobilipa.base_url', 'https://mobilipa.store/api/v1'), '/');

        if (str_ends_with($baseUrl, 'order-status.php')) {
            return $baseUrl;
        }

        if (str_ends_with($baseUrl, '/api/v1') || str_ends_with($baseUrl, '/v1')) {
            return $baseUrl.'/order-status.php';
        }

        return $baseUrl.'/api/v1/order-status.php';
    }

    private function getHeaders(): array
    {
        $apiKey = (string) config('services.mobilipa.api_key');
        $headers = [
            'Accept' => 'application/json',
            'X-API-KEY' => $apiKey,
        ];

        if (filled($apiKey)) {
            $headers['Authorization'] = 'Bearer '.$apiKey;
        }

        return $headers;
    }

    private function decodeResponse(Response $response): array
    {
        if (! $response->successful()) {
            return [
                'status' => 'error',
                'message' => $response->json('message') ?? $response->body() ?? 'Unable to reach Mobilipa.',
                'http_status' => $response->status(),
                'data' => $response->json('data'),
            ];
        }

        $json = $response->json();
        if (! is_array($json)) {
            return [
                'status' => 'error',
                'message' => 'Invalid JSON response from Mobilipa.',
                'http_status' => $response->status(),
                'data' => null,
            ];
        }

        if (isset($json['success'])) {
            if ($json['success'] === true) {
                if (isset($json['status']) && ! in_array(strtolower((string) $json['status']), ['success', 'error', 'failed'], true)) {
                    $json['payment_status'] = $json['status'];
                }
                $json['status'] = 'success';
            } else {
                $json['status'] = 'error';
            }
        }

        return $json;
    }
}
