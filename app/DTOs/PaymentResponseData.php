<?php

namespace App\DTOs;

readonly class PaymentResponseData
{
    public function __construct(
        public bool $success,
        public string $orderId,
        public string $status,
        public ?string $message = null,
        public ?string $checkoutUrl = null
    ) {}
}
