<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Setting;
use InvalidArgumentException;

class PaymentGatewayFactory
{
    public static function getActiveGatewayName(): string
    {
        return Setting::get('active_payment_gateway', 'sonicpesa');
    }

    public static function make(?string $name = null): object
    {
        $gateway = $name ?: self::getActiveGatewayName();

        return match ($gateway) {
            'mobilipa' => app(MobilipaService::class),
            'sonicpesa' => app(SonicPesaService::class),
            default => throw new InvalidArgumentException("Unsupported gateway: {$gateway}"),
        };
    }
}
