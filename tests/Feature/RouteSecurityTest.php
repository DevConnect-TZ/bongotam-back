<?php

namespace Tests\Feature;

use App\Models\AuthSession;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Video;
use App\Services\ApiSessionService;
use App\Services\FirebaseIdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RouteSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config([
            'services.sonicpesa.api_key' => 'test-key',
            'services.sonicpesa.api_secret' => 'test-secret',
            'services.mobilipa.api_key' => 'test-key',
            'services.mobilipa.webhook_secret' => 'test-secret',
        ]);
    }

    private function token(User $user): string
    {
        return app(ApiSessionService::class)->issueSession($user, Request::create('/'))['access_token'];
    }

    public function test_every_private_api_route_rejects_guests_and_every_admin_route_rejects_regular_users(): void
    {
        $public = ['api/uptime', 'api/frontend/security', 'api/auth/session', 'api/auth/refresh', 'api/auth/logout', 'api/payments/sonicpesa/webhook', 'api/payments/mobilipa/webhook'];
        $token = $this->token(User::factory()->create());
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/') || in_array($route->uri(), $public, true)) {
                continue;
            }
            $this->assertContains('api.auth', $route->gatherMiddleware(), $route->uri());
            $uri = '/'.preg_replace('/\{[^}]+\}/', '1', $route->uri());
            $method = $route->methods()[0];
            $this->json($method, $uri)->assertUnauthorized();
            if (in_array('api.admin', $route->gatherMiddleware(), true)) {
                $this->json($method, $uri, [], ['Authorization' => 'Bearer '.$token])->assertForbidden();
            }
        }
    }

    public function test_banned_revoked_expired_and_forged_sessions_are_rejected(): void
    {
        $user = User::factory()->create();
        $token = $this->token($user);
        $user->update(['status' => 'banned']);
        $this->withToken($token)->getJson('/api/videos')->assertUnauthorized();
        $user->update(['status' => 'active']);
        AuthSession::query()->update(['revoked_at' => now()]);
        $this->withToken($token)->getJson('/api/videos')->assertUnauthorized();
        $token = $this->token($user);
        $this->travel(31)->seconds();
        $this->withToken($token)->getJson('/api/videos')->assertUnauthorized();
        $this->withToken('forged')->getJson('/api/videos')->assertUnauthorized();
    }

    public function test_admin_permission_is_checked_again_after_demotion(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $this->token($admin);
        $this->withToken($token)->getJson('/api/users')->assertOk();
        $admin->update(['role' => 'user']);
        $this->withToken($token)->getJson('/api/users')->assertForbidden();
    }

    public function test_users_cannot_access_other_users_or_payment_orders(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $payment = $this->payment($other);
        $this->withToken($this->token($user));
        $this->getJson('/api/users/lookup?email='.urlencode($other->email))->assertForbidden();
        $this->postJson('/api/users', ['email' => $other->email, 'name' => 'Hijacked'])->assertForbidden();
        $this->getJson('/api/payments/orders/'.$payment->transaction_id)->assertForbidden();
        $this->getJson('/api/transactions?user_id='.$other->id)->assertOk()->assertExactJson([]);
    }

    public function test_subscription_cannot_be_granted_by_claiming_a_payment(): void
    {
        $user = User::factory()->create();
        $this->withToken($this->token($user))->postJson('/api/subscription/wakubwa/subscribe', ['transaction_id' => 'invented', 'amount' => 1])->assertStatus(409);
        $this->assertFalse($user->fresh()->isWakubwaSubscribed());
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_payment_order_rejects_underpayment_missing_video_and_wrong_zone(): void
    {
        $user = User::factory()->create();
        $video = Video::create(['title' => 'Paid video', 'price' => 400, 'zone' => 'connection']);
        $this->withToken($this->token($user));
        $data = ['buyer_name' => 'Buyer', 'buyer_phone' => '0655123456', 'amount' => 1, 'currency' => 'TZS', 'zone' => 'connection', 'item_id' => (string) $video->id];
        $this->postJson('/api/payments/order', $data)->assertUnprocessable();
        $this->postJson('/api/payments/order', [...$data, 'amount' => 400, 'item_id' => '999'])->assertUnprocessable();
        $this->postJson('/api/payments/order', [...$data, 'amount' => 400, 'currency' => 'USD'])->assertUnprocessable();
        $this->postJson('/api/payments/order', [...$data, 'zone' => 'wakubwa', 'amount' => 3000])->assertUnprocessable();
        Http::assertNothingSent();
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_unpaid_connection_and_expired_wakubwa_videos_cannot_be_streamed_even_if_wakubwa_price_is_zero(): void
    {
        $user = User::factory()->create();
        $video = Video::create(['title' => 'Paid', 'price' => 400, 'zone' => 'connection', 'video_link' => 'https://example.com/private.mp4']);
        $vip = Video::create(['title' => 'VIP', 'price' => 0, 'zone' => 'wakubwa', 'video_link' => 'https://example.com/vip.mp4']);
        $this->withToken($this->token($user));
        $this->postJson('/api/videos/'.$video->id.'/stream')->assertForbidden()->assertJsonMissingPath('url');
        $this->postJson('/api/videos/'.$vip->id.'/stream')->assertForbidden()->assertJsonMissingPath('url');
        $user->update(['unlocked_connection_videos' => [(string) $video->id], 'wakubwa_subscription_expires_at' => now()->addMonth()]);
        $this->postJson('/api/videos/'.$video->id.'/stream')->assertOk();
        $this->postJson('/api/videos/'.$vip->id.'/stream')->assertOk();
        $this->getJson('/api/videos')->assertJsonMissingPath('0.video_link');
    }

    public function test_unsigned_or_invalid_webhooks_cannot_change_payments(): void
    {
        $user = User::factory()->create();
        $payment = $this->payment($user);
        foreach (['mobilipa', 'sonicpesa'] as $provider) {
            $this->postJson('/api/payments/'.$provider.'/webhook', ['order_id' => $payment->transaction_id, 'status' => 'PAID'])->assertUnauthorized();
        }
        $this->assertSame('PENDING', $payment->fresh()->status);
        $this->assertFalse($user->fresh()->isWakubwaSubscribed());
    }

    public function test_replayed_signed_callbacks_grant_a_subscription_only_once(): void
    {
        $user = User::factory()->create();
        $payment = $this->payment($user);
        $payload = ['order_id' => $payment->transaction_id, 'status' => 'PAID', 'amount' => 3000, 'currency' => 'TZS'];
        $this->signedWebhook($payload)->assertOk();
        $expires = $user->fresh()->wakubwa_subscription_expires_at;
        $this->signedWebhook($payload)->assertOk();
        $this->assertTrue($expires->equalTo($user->fresh()->wakubwa_subscription_expires_at));
        $this->assertNotNull($payment->fresh()->access_granted_at);
        $this->withToken($this->token($user));
        $user->update(['wakubwa_subscription_expires_at' => now()->subDay()]);
        $this->getJson('/api/payments/orders/'.$payment->transaction_id)->assertOk();
        $this->assertFalse($user->fresh()->isWakubwaSubscribed());
    }

    public function test_signed_callbacks_must_match_the_provider_and_original_amount(): void
    {
        $user = User::factory()->create();
        $payment = $this->payment($user);
        $this->signedWebhook(['order_id' => $payment->transaction_id, 'status' => 'PAID', 'amount' => 1])->assertUnprocessable();
        $payment->update(['provider' => 'sonicpesa']);
        $this->signedWebhook(['order_id' => $payment->transaction_id, 'status' => 'PAID', 'amount' => 3000])->assertNotFound();
        $this->assertFalse($user->fresh()->isWakubwaSubscribed());
    }

    public function test_cross_origin_session_requests_are_rejected(): void
    {
        foreach (['/api/auth/session', '/api/auth/refresh', '/api/auth/logout', '/connection/session', '/connection/access', '/connection/logout'] as $uri) {
            $this->withHeader('Origin', 'https://attacker.example')->postJson($uri)->assertForbidden();
        }
        $this->withHeader('Origin', config('cors.allowed_origins.0'))->postJson('/api/auth/session')->assertUnprocessable();
    }

    public function test_unverified_firebase_identity_cannot_claim_an_admin_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->mock(FirebaseIdentityService::class, function ($mock) use ($admin): void {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('lookupByIdToken')->andReturn(['email' => $admin->email, 'email_verified' => false]);
        });
        $this->postJson('/api/auth/session', ['id_token' => 'unverified'])->assertForbidden();
        $this->postJson('/connection/session', ['id_token' => 'unverified'])->assertForbidden();
        $this->assertDatabaseCount('auth_sessions', 0);
    }

    public function test_auth_endpoints_are_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->postJson('/api/auth/session')->assertUnprocessable();
        }
        $this->postJson('/api/auth/session')->assertTooManyRequests();
    }

    public function test_logout_with_an_incorrect_refresh_secret_cannot_revoke_another_session(): void
    {
        $user = User::factory()->create();
        $this->token($user);
        $session = AuthSession::firstOrFail();
        $this->withUnencryptedCookie('connection_refresh_token', $session->id.'.wrong-secret')->postJson('/api/auth/logout')->assertOk();
        $this->assertNull($session->fresh()->revoked_at);
    }

    public function test_web_dashboard_rejects_stale_non_admin_or_banned_sessions(): void
    {
        $user = User::factory()->create();
        $this->withSession(['connection_admin_id' => $user->id, 'connection_admin_email' => $user->email])
            ->get('/connection')->assertRedirect('/connection/login');
        $this->post('/connection/gateway', ['provider' => 'mobilipa'])->assertRedirect('/connection/login');
        $user->update(['role' => 'admin', 'status' => 'banned']);
        $this->withSession(['connection_admin_id' => $user->id, 'connection_admin_email' => $user->email])
            ->get('/connection')->assertRedirect('/connection/login');
    }

    public function test_laravel_private_file_routes_require_a_signature(): void
    {
        $this->get('/storage/private-video.mp4')->assertForbidden();
        $this->put('/storage/private-video.mp4', ['content' => 'unauthorized'])->assertForbidden();
    }

    public function test_uploads_reject_path_traversal_and_use_a_safe_server_detected_extension(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $this->withToken($this->token($admin));
        $this->post('/api/uploads/video', [
            'file' => UploadedFile::fake()->create('clip.mp4', 1, 'video/mp4'),
            'folder' => '../../public',
        ], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->post('/api/uploads/video', [
            'file' => UploadedFile::fake()->create('clip.php', 1, 'video/mp4'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();
        $response = $this->post('/api/uploads/video', [
            'file' => UploadedFile::fake()->create('clip.mp4', 1, 'video/mp4'),
        ], ['Accept' => 'application/json'])->assertOk();
        $path = $response->json('public_id');
        $this->assertStringEndsWith('.mp4', $path);
        Storage::disk('public')->assertExists($path);
    }

    private function payment(User $user): Transaction
    {
        return Transaction::create(['user_id' => (string) $user->id, 'user_email' => $user->email, 'amount' => 3000, 'currency' => 'TZS', 'zone' => 'wakubwa', 'transaction_id' => 'security-order', 'provider' => 'mobilipa', 'status' => 'PENDING']);
    }

    private function signedWebhook(array $payload): TestResponse
    {
        $raw = json_encode($payload);

        return $this->call('POST', '/api/payments/mobilipa/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_MOBILIPA_SIGNATURE' => hash_hmac('sha256', $raw, 'test-secret')], $raw);
    }
}
