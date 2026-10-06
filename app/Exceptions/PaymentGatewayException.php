<?php

namespace App\Exceptions;

use Exception;

class PaymentGatewayException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $gateway = '',
        public readonly array $context = [],
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
