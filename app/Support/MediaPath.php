<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class MediaPath
{
    public static function normalizeForStorage(?string $value): ?string
    {
        $value = self::clean($value);

        if ($value === null) {
            return null;
        }

        return self::extractLocalRelativePath($value) ?? $value;
    }

    public static function toPublicUrl(?string $value): ?string
    {
        $value = self::clean($value);

        if ($value === null) {
            return null;
        }

        $relativePath = self::extractLocalRelativePath($value);

        if ($relativePath === null) {
            return $value;
        }

        return Storage::disk('public')->url($relativePath);
    }

    public static function deleteIfLocal(?string $value): void
    {
        $relativePath = self::extractLocalRelativePath($value);

        if ($relativePath === null) {
            return;
        }

        Storage::disk('public')->delete($relativePath);
    }

    private static function extractLocalRelativePath(?string $value): ?string
    {
        $value = self::clean($value);

        if ($value === null) {
            return null;
        }

        $value = str_replace('\\', '/', $value);

        if (preg_match('#^https?://#i', $value) === 1) {
            $host = parse_url($value, PHP_URL_HOST);
            $appHost = parse_url(config('app.url'), PHP_URL_HOST);
            $path = (string) parse_url($value, PHP_URL_PATH);

            if ($host !== null && $appHost !== null && strcasecmp($host, $appHost) !== 0) {
                return null;
            }

            return self::extractFromStoragePath($path);
        }

        if (str_starts_with($value, '/storage/') || str_starts_with($value, 'storage/')) {
            return self::extractFromStoragePath('/'.ltrim($value, '/'));
        }

        if (preg_match('#^[A-Za-z0-9_\-/]+\.[A-Za-z0-9]+$#', $value) === 1) {
            return ltrim($value, '/');
        }

        return null;
    }

    private static function extractFromStoragePath(string $path): ?string
    {
        $normalized = str_replace('\\', '/', $path);
        $marker = '/storage/';
        $position = strpos($normalized, $marker);

        if ($position === false) {
            return null;
        }

        $relativePath = substr($normalized, $position + strlen($marker));

        return $relativePath === '' ? null : ltrim($relativePath, '/');
    }

    private static function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
