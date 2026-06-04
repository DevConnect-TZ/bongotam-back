<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthenticateApiAccessToken;
use App\Http\Middleware\EnsureApiAdmin;
use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentGatewaySwitchingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is required for database-backed gateway switching tests.');
        }

        parent::setUp();

        config([
            'services.sonicpesa.base_url' => 'https://api.sonicpesa.com',
            'services.sonicpesa.api_key' => 'test-sp-key',
            'services.sonicpesa.api_secret' => 'test-sp-secret',
            'services.mobilipa.base_url' => 'https://api.mobilipa.store',
            'services.mobilipa.api_key' => 'test-mobilipa-key',
        ]);

        $this->withoutMiddleware([
            AuthenticateApiAccessToken::class,
            EnsureApiAdmin::class,
        ]);
    }

    public function test_admin_can_switch_active_payment_gateway(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $response = $this->actingAs($admin)->putJson('/api/payments/gateway', [
            'provider' => 'mobilipa',
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'active_provider' => 'mobilipa',
            ]);

        $this->assertDatabaseHas('app_settings', [
            'key' => 'active_payment_gateway',
        ]);
    }

    public function test_default_gateway_is_sonicpesa_when_no_setting_exists(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'https://api.sonicpesa.com/*' => Http::response([
                'status' => 'success',
                'message' => 'ok',
                'data' => [
                    'order_id' => 'sp_default',
                    'reference' => 'REF001',
                    'amount' => 5000,
                    'currency' => 'TZS',
                    'payment_status' => 'PENDING',
                    'status' => 'PENDING',
                    'msisdn' => '255797455136',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->postJson('/api/payments/sonicpesa/order', [
            'buyer_name' => 'Test',
            'buyer_phone' => '0797455136',
            'amount' => 5000,
            'currency' => 'TZS',
        ]);

        $response->assertCreated();
        Http::assertSent(fn ($request) => $request->url() === 'https://api.sonicpesa.com/api/v1/payment/create_order');
    }

    public function test_it_routes_to_mobilipa_when_gateway_is_set_to_mobilipa(): void
    {
        $user = User::factory()->create();
        AppSetting::updateOrCreate(
            ['key' => 'active_payment_gateway'],
            ['value' => ['provider' => 'mobilipa']]
        );

        Http::fake([
            'https://api.mobilipa.store/*' => Http::response([
                'status' => 'success',
                'message' => 'ok',
                'data' => [
                    'order_id' => 'mp_switched',
                    'reference' => 'REF002',
                    'amount' => 5000,
                    'currency' => 'TZS',
                    'payment_status' => 'PENDING',
                    'status' => 'PENDING',
                    'msisdn' => '255797455136',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->postJson('/api/payments/sonicpesa/order', [
            'buyer_name' => 'Test',
            'buyer_phone' => '0797455136',
            'amount' => 5000,
            'currency' => 'TZS',
        ]);

        $response->assertCreated();
        Http::assertSent(fn ($request) => $request->url() === 'https://api.mobilipa.store/v1/payment/create_order');
    }
}
