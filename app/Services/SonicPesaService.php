<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class SonicPesaService
{
    public function createOrder(array $payload): array
    {
        try {
            $response = Http::acceptJson()
                ->timeout(45)
                ->withHeaders([
                    'X-API-KEY' => config('services.sonicpesa.api_key'),
                ])
                ->post($this->createOrderUrl(), $payload);
        } catch (ConnectionException $exception) {
            return [
                'status' => 'error',
                'message' => 'Unable to connect to SonicPesa. Please try again shortly.',
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
            $response = Http::acceptJson()
                ->timeout(45)
                ->withHeaders([
                    'X-API-KEY' => config('services.sonicpesa.api_key'),
                ])
                ->post($this->orderStatusUrl(), [
                    'order_id' => $orderId,
                ]);
        } catch (ConnectionException $exception) {
            return [
                'status' => 'error',
                'message' => 'Unable to check SonicPesa order status. Please try again shortly.',
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
        return filled(config('services.sonicpesa.api_key')) && filled(config('services.sonicpesa.api_secret'));
    }

    public function verifyWebhookSignature(string $payloadRaw, ?string $signature): bool
    {
        if (! filled($signature) || ! filled(config('services.sonicpesa.api_secret'))) {
            return false;
        }

        $expected = hash_hmac('sha256', $payloadRaw, (string) config('services.sonicpesa.api_secret'));

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

        if (str_starts_with($digits, '7') && strlen($digits) === 9) {
            return '255'.$digits;
        }

        return null;
    }

    private function createOrderUrl(): string
    {
        return rtrim((string) config('services.sonicpesa.base_url'), '/').'/api/v1/payment/create_order';
    }

    private function orderStatusUrl(): string
    {
        return rtrim((string) config('services.sonicpesa.base_url'), '/').'/api/v1/payment/order_status';
    }

    private function decodeResponse(Response $response): array
    {
        if (! $response->successful()) {
            return [
                'status' => 'error',
                'message' => $response->json('message') ?? $response->body() ?? 'Unable to reach SonicPesa.',
                'http_status' => $response->status(),
                'data' => $response->json('data'),
            ];
        }

        return $response->json();
    }
}
