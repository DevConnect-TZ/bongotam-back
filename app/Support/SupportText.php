<?php

namespace App\Support;

class SupportText
{
    public static function normalize(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (class_exists(\Normalizer::class)) {
            $value = \Normalizer::normalize($value, \Normalizer::FORM_KC) ?: $value;
        }

        return trim(preg_replace('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}-\x{009F}\p{Cf}]/u', '', $value) ?? '');
    }
}
