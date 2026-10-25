<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ApiSessionService;
use App\Services\FirebaseIdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class SubscriptionAutoBanTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_excessive_subscription_auto_bans_the_user_on_the_next_request(): void
    {
        $user = User::factory()->create(['wakubwa_subscription_expires_at' => now()->addDays(150)]);
        $token = $this->token($user);

        $this->withToken($token)->getJson('/api/videos')->assertUnauthorized();

        $this->assertSame('banned', $user->fresh()->status);
    }

    public function test_a_normal_subscription_is_not_banned(): void
    {
        $user = User::factory()->create(['wakubwa_subscription_expires_at' => now()->addDays(30)]);

        $this->withToken($this->token($user))->getJson('/api/videos')->assertOk();

        $this->assertSame('active', $user->fresh()->status);
    }

    public function test_admins_are_exempt_from_the_automatic_ban(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'wakubwa_subscription_expires_at' => now()->addDays(150),
        ]);

        $this->withToken($this->token($admin))->getJson('/api/videos')->assertOk();

        $this->assertSame('active', $admin->fresh()->status);
    }

    public function test_login_auto_bans_an_excessive_subscription(): void
    {
        $user = User::factory()->create(['wakubwa_subscription_expires_at' => now()->addMonths(5)]);

        $this->mock(FirebaseIdentityService::class, function ($mock) use ($user): void {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('lookupByIdToken')->andReturn([
                'uid' => 'firebase-uid',
                'email' => $user->email,
                'email_verified' => true,
                'name' => $user->name,
            ]);
        });

        $this->postJson('/api/auth/session', ['id_token' => 'valid-token'])
            ->assertForbidden()
            ->assertJson(['message' => 'This account is blocked.']);

        $this->assertSame('banned', $user->fresh()->status);
    }

    private function token(User $user): string
    {
        return app(ApiSessionService::class)->issueSession($user, Request::create('/'))['access_token'];
    }
}
