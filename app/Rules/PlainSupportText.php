<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PlainSupportText implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        if (preg_match('/[<>]/u', $value)) {
            $fail('Use plain text only. HTML and embedded content are not allowed.');
        }

        if (preg_match('~(?:[a-z][a-z0-9+.-]*://|(?:https?|ftp|mailto|tel|data|javascript):|www\.|//[\p{L}\p{N}]|(?:[\p{L}\p{N}](?:[\p{L}\p{N}-]*[\p{L}\p{N}])?\.)+(?:[\p{L}]{2,63}|xn--[a-z0-9-]+)(?![\p{L}\p{N}-])|(?:\d{1,3}\.){3}\d{1,3}|localhost(?::\d+|/))~iu', $value)) {
            $fail('Links are not allowed. Please remove them and send your message again.');
        }
    }
}
