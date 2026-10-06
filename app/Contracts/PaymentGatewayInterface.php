<?php

namespace App\Contracts;

use App\DTOs\PaymentRequestData;
use App\DTOs\PaymentResponseData;

interface PaymentGatewayInterface
{
    public function initiatePayment(PaymentRequestData $data): PaymentResponseData;

    public function checkStatus(string $orderId): array;

    public function verifyWebhook(array $payload, ?string $signature = null): bool;
}
