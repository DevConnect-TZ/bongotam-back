<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthenticateApiAccessToken;
use App\Http\Middleware\EnsureApiAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WakubwaSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is required for database-backed subscription tests.');
        }

        parent::setUp();

        $this->withoutMiddleware([
            AuthenticateApiAccessToken::class,
            EnsureApiAdmin::class,
        ]);
    }

    public function test_it_returns_and_updates_wakubwa_price(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->getJson('/api/subscription/wakubwa/price');
        $response->assertOk()->assertJson(['price' => 3000]);

        $updateResponse = $this->actingAs($admin)->putJson('/api/subscription/wakubwa/price', [
            'price' => 5000,
        ]);
        $updateResponse->assertOk()->assertJson(['price' => 5000]);

        $this->actingAs($admin)->getJson('/api/subscription/wakubwa/price')
            ->assertOk()
            ->assertJson(['price' => 5000]);
    }

    public function test_it_fetches_subscribers_list_for_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $activeSubscriber = User::factory()->create([
            'email' => 'active@example.com',
            'wakubwa_subscription_expires_at' => now()->addDays(15),
        ]);

        $expiredSubscriber = User::factory()->create([
            'email' => 'expired@example.com',
            'wakubwa_subscription_expires_at' => now()->subDays(5),
        ]);

        User::factory()->create([
            'email' => 'nonsubscriber@example.com',
            'wakubwa_subscription_expires_at' => null,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/subscription/wakubwa/subscribers');

        $response->assertOk();
        $data = $response->json();

        $this->assertCount(2, $data);
        $this->assertEquals('active@example.com', $data[0]['email']);
        $this->assertTrue($data[0]['is_active']);
        $this->assertEquals('expired@example.com', $data[1]['email']);
        $this->assertFalse($data[1]['is_active']);
    }

    public function test_admin_can_grant_subscription_to_a_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create([
            'email' => 'target@example.com',
            'wakubwa_subscription_expires_at' => null,
        ]);

        $response = $this->actingAs($admin)->postJson("/api/users/{$user->id}/grant-subscription", [
            'months' => 1,
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'user' => [
                    'id' => $user->id,
                    'email' => 'target@example.com',
                    'is_active' => true,
                ],
            ]);

        $this->assertTrue($user->fresh()->isWakubwaSubscribed());
    }
}

