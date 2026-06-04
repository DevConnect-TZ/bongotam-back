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
            $response = Http::acceptJson()
                ->timeout(45)
                ->withHeaders([
                    'X-API-KEY' => config('services.mobilipa.api_key'),
                ])
                ->post($this->createOrderUrl(), $payload);
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
            $response = Http::acceptJson()
                ->timeout(45)
                ->withHeaders([
                    'X-API-KEY' => config('services.mobilipa.api_key'),
                    'Content-Type' => 'application/json',
                ])
                ->withBody(json_encode([
                    'order_id' => $orderId,
                ]), 'application/json')
                ->get($this->orderStatusUrl());
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
        return rtrim((string) config('services.mobilipa.base_url', 'https://api.mobilipa.store'), '/').'/v1/payment/create_order';
    }

    private function orderStatusUrl(): string
    {
        return rtrim((string) config('services.mobilipa.base_url', 'https://api.mobilipa.store'), '/').'/v1/payment/status';
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

        return $response->json();
    }
}
