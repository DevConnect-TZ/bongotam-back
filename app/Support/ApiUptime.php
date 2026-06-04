<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ApiUptime
{
    public function snapshot(): array
    {
        $checkedAt = CarbonImmutable::now();
        $startedAt = $this->startedAt($checkedAt);
        $uptimeSeconds = $startedAt->diffInSeconds($checkedAt, true);

        return [
            'status' => 'up',
            'status_label' => 'UP',
            'message' => 'Hoora! The API is up.',
            'uptime_seconds' => $uptimeSeconds,
            'uptime_human' => $this->formatDuration($uptimeSeconds),
            'started_at' => $startedAt->toIso8601String(),
            'started_at_human' => $this->formatTimestamp($startedAt),
            'checked_at' => $checkedAt->toIso8601String(),
            'checked_at_human' => $this->formatTimestamp($checkedAt),
        ];
    }

    private function startedAt(CarbonImmutable $fallback): CarbonImmutable
    {
        $path = $this->startedAtPath();

        File::ensureDirectoryExists(dirname($path));

        if (! File::exists($path) || trim((string) File::get($path)) === '') {
            File::put($path, $fallback->toIso8601String(), true);
        }

        $storedValue = trim((string) File::get($path));

        try {
            return CarbonImmutable::parse($storedValue);
        } catch (\Throwable) {
            File::put($path, $fallback->toIso8601String(), true);

            return $fallback;
        }
    }

    private function startedAtPath(): string
    {
        $appName = Str::slug((string) config('app.name', 'laravel'));

        return sys_get_temp_dir().DIRECTORY_SEPARATOR.$appName.'-api-started-at.txt';
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds === 0) {
            return '0 seconds';
        }

        return CarbonInterval::seconds($seconds)
            ->cascade()
            ->forHumans([
                'short' => true,
                'parts' => 3,
                'join' => true,
            ]);
    }

    private function formatTimestamp(CarbonImmutable $timestamp): string
    {
        return $timestamp
            ->setTimezone(config('app.timezone'))
            ->format('M j, Y g:i:s A T');
    }
}
