<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

class PaymentLogger
{
    public static function logInitiation(string $gateway, string $orderId, float $amount, string $phone): void
    {
        Log::info("[Payment] Initiated payment via {$gateway}", [
            'order_id' => $orderId,
            'amount' => $amount,
            'phone' => substr($phone, 0, 4) . '****',
        ]);
    }

    public static function logSuccess(string $gateway, string $orderId): void
    {
        Log::info("[Payment] Payment successful via {$gateway}", [
            'order_id' => $orderId,
        ]);
    }
}
