<?php

namespace Tests\Feature;

use App\Models\AuthSession;
use App\Models\LoginEvent;
use App\Models\User;
use App\Services\ApiSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class SessionSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_stores_the_device_and_records_a_login_event(): void
    {
        $user = User::factory()->create();

        $this->issueSession($user, 'device-alpha');

        $session = AuthSession::where('user_id', $user->id)->firstOrFail();

        $this->assertSame('device-alpha', $session->device_id);
        $this->assertDatabaseHas('login_events', [
            'user_id' => $user->id,
            'auth_session_id' => $session->id,
            'device_id' => 'device-alpha',
            'event' => LoginEvent::EVENT_LOGIN,
        ]);
    }

    public function test_a_third_login_revokes_the_oldest_active_session(): void
    {
        $user = User::factory()->create();

        $this->issueSession($user, 'device-one');
        AuthSession::where('device_id', 'device-one')->update(['last_used_at' => now()->subMinutes(10)]);

        $this->issueSession($user, 'device-two');
        AuthSession::where('device_id', 'device-two')->update(['last_used_at' => now()->subMinutes(5)]);

        $this->issueSession($user, 'device-three');

        $this->assertSame(2, AuthSession::where('user_id', $user->id)->whereNull('revoked_at')->count());
        $this->assertNotNull(AuthSession::where('device_id', 'device-one')->firstOrFail()->revoked_at);
        $this->assertNull(AuthSession::where('device_id', 'device-two')->firstOrFail()->revoked_at);
        $this->assertNull(AuthSession::where('device_id', 'device-three')->firstOrFail()->revoked_at);
        $this->assertDatabaseHas('login_events', [
            'user_id' => $user->id,
            'device_id' => 'device-one',
            'event' => LoginEvent::EVENT_SESSION_REVOKED_LIMIT,
        ]);
    }

    public function test_admins_can_see_flagged_accounts_with_multiple_devices_and_ips(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();

        foreach (['device-a', 'device-b', 'device-c'] as $index => $deviceId) {
            LoginEvent::create([
                'user_id' => $user->id,
                'device_id' => $deviceId,
                'ip_address' => '203.0.113.'.($index + 1),
                'user_agent' => 'Test Agent',
                'event' => LoginEvent::EVENT_LOGIN,
            ]);
        }

        LoginEvent::create([
            'user_id' => $user->id,
            'device_id' => 'device-a',
            'ip_address' => '203.0.113.4',
            'user_agent' => 'Test Agent',
            'event' => LoginEvent::EVENT_LOGIN,
        ]);

        $response = $this->withToken($this->token($admin))
            ->getJson('/api/users/security')
            ->assertOk();

        $account = collect($response->json())->firstWhere('user.id', $user->id);

        $this->assertNotNull($account);
        $this->assertTrue($account['suspicious']);
        $this->assertSame(3, $account['last_24h']['distinct_devices']);
        $this->assertSame(4, $account['last_24h']['distinct_ips']);
        $this->assertContains('multiple_devices', $account['reasons']);
        $this->assertContains('multiple_ips', $account['reasons']);
        $this->assertCount(4, $account['recent_login_events']);
    }

    public function test_regular_users_cannot_see_the_security_overview(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->token($user))
            ->getJson('/api/users/security')
            ->assertForbidden();
    }

    private function issueSession(User $user, string $deviceId): void
    {
        app(ApiSessionService::class)->issueSession($user, Request::create('/', 'POST', [
            'device_id' => $deviceId,
        ]));
    }

    private function token(User $user): string
    {
        return app(ApiSessionService::class)->issueSession($user, Request::create('/'))['access_token'];
    }
}
