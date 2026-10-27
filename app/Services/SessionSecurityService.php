<?php

namespace App\Services;

use App\Models\AuthSession;
use App\Models\LoginEvent;
use App\Models\User;
use Illuminate\Support\Collection;

class SessionSecurityService
{
    public const MAX_ACTIVE_SESSIONS = 2;

    public const DEVICE_FLAG_THRESHOLD = 3;

    public const IP_FLAG_THRESHOLD = 4;

    public function enforceActiveSessionLimit(User $user): void
    {
        $activeSessions = $this->activeSessionModels($user, 'asc');
        $excess = $activeSessions->count() - (self::MAX_ACTIVE_SESSIONS - 1);

        if ($excess <= 0) {
            return;
        }

        foreach ($activeSessions->take($excess) as $session) {
            $session->forceFill(['revoked_at' => now()])->save();
            $this->recordSessionEvent($session, LoginEvent::EVENT_SESSION_REVOKED_LIMIT);
        }
    }

    public function recordLogin(AuthSession $session): LoginEvent
    {
        return $this->recordSessionEvent($session, LoginEvent::EVENT_LOGIN);
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(User $user): array
    {
        $activeSessions = $this->activeSessionModels($user);
        $since = now()->subDay();

        $events = LoginEvent::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', $since);

        $logins = (clone $events)->where('event', LoginEvent::EVENT_LOGIN)->count();
        $distinctDevices = (clone $events)->whereNotNull('device_id')->distinct()->count('device_id');
        $distinctIps = (clone $events)->whereNotNull('ip_address')->distinct()->count('ip_address');

        $reasons = [];

        if ($distinctDevices >= self::DEVICE_FLAG_THRESHOLD) {
            $reasons[] = 'multiple_devices';
        }

        if ($distinctIps >= self::IP_FLAG_THRESHOLD) {
            $reasons[] = 'multiple_ips';
        }

        return [
            'active_sessions' => $activeSessions->map(fn (AuthSession $session): array => [
                'id' => $session->id,
                'device_id' => $session->device_id,
                'ip_address' => $session->ip_address,
                'user_agent' => $session->user_agent,
                'last_used_at' => $session->last_used_at?->toIso8601String(),
                'created_at' => $session->created_at?->toIso8601String(),
            ])->values()->all(),
            'active_session_count' => $activeSessions->count(),
            'logins_24h' => $logins,
            'distinct_devices_24h' => $distinctDevices,
            'distinct_ips_24h' => $distinctIps,
            'suspicious' => $reasons !== [],
            'reasons' => $reasons,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentLoginEvents(User $user, int $limit = 20): array
    {
        return LoginEvent::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'auth_session_id', 'device_id', 'ip_address', 'user_agent', 'event', 'created_at'])
            ->map(fn (LoginEvent $event): array => [
                'id' => $event->id,
                'session_id' => $event->auth_session_id,
                'device_id' => $event->device_id,
                'ip_address' => $event->ip_address,
                'user_agent' => $event->user_agent,
                'event' => $event->event,
                'created_at' => $event->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, AuthSession>
     */
    private function activeSessionModels(User $user, string $direction = 'desc'): Collection    {
        $order = $direction === 'asc' ? 'asc' : 'desc';

        return AuthSession::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->where('refresh_expires_at', '>', now())
            ->orderByRaw("COALESCE(last_used_at, created_at) {$order}")
            ->get([
                'id',
                'user_id',
                'device_id',
                'ip_address',
                'user_agent',
                'last_used_at',
                'refresh_expires_at',
                'created_at',
            ]);
    }

    private function recordSessionEvent(AuthSession $session, string $event): LoginEvent
    {
        return LoginEvent::create([
            'user_id' => $session->user_id,
            'auth_session_id' => $session->id,
            'device_id' => $session->device_id,
            'ip_address' => $session->ip_address,
            'user_agent' => $session->user_agent,
            'event' => $event,
        ]);
    }
}
