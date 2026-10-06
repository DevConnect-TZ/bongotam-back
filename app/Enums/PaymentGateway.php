<?php

namespace App\Enums;

enum PaymentGateway: string
{
    case SONICPESA = 'sonicpesa';
    case MOBILIPA = 'mobilipa';

    public function label(): string
    {
        return match ($this) {
            self::SONICPESA => 'SonicPesa Mobile Payment',
            self::MOBILIPA => 'Mobilipa Instant Gateway',
        };
    }
}
