<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthenticateApiAccessToken;
use App\Http\Middleware\EnsureApiAdmin;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MobilipaPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is required for database-backed Mobilipa payment tests.');
        }

        parent::setUp();

        config([
            'services.sonicpesa.base_url' => 'https://api.sonicpesa.com',
            'services.sonicpesa.api_key' => 'test-sp-key',
            'services.sonicpesa.api_secret' => 'test-sp-secret',
            'services.mobilipa.base_url' => 'https://mobilipa.store/api/v1',
            'services.mobilipa.api_key' => 'test-mobilipa-key',
            'services.mobilipa.webhook_secret' => 'test-mobilipa-webhook-secret',
        ]);

        $this->withoutMiddleware([
            AuthenticateApiAccessToken::class,
            EnsureApiAdmin::class,
        ]);
    }

    public function test_it_creates_a_mobilipa_order_and_persists_the_pending_transaction(): void
    {
        $user = User::factory()->create([
            'email' => 'buyer@example.com',
        ]);

        Http::fake([
            'https://mobilipa.store/api/v1/request-payment.php*' => Http::response([
                'success' => true,
                'order_id' => 'mp_69e9623649553',
                'reference' => 'M20515045387',
                'status' => 'PENDING',
                'resultcode' => '000',
                'message' => 'USSD push sent to 255797455136. Waiting for customer PIN.',
                'selcom_reference' => 'S20754484120',
            ], 200),
        ]);

        $response = $this->actingAs($user)->postJson('/api/payments/mobilipa/order', [
            'user_email' => $user->email,
            'buyer_name' => 'John Doe',
            'buyer_phone' => '0797455136',
            'amount' => 10000,
            'currency' => 'TZS',
            'type' => 'PURCHASE_CONNECTION',
            'item_id' => '17',
            'item_title' => 'Premium Connection Video',
            'zone' => 'connection',
            'provider' => 'mobilipa',
        ]);

        $response
            ->assertCreated()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'order_id' => 'mp_69e9623649553',
                    'reference' => 'M20515045387',
                    'amount' => 10000,
                    'currency' => 'TZS',
                    'payment_status' => 'PENDING',
                    'status' => 'PENDING',
                    'item_id' => '17',
                    'zone' => 'connection',
                    'access_granted' => false,
                ],
            ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://mobilipa.store/api/v1/request-payment.php'
                && $request->hasHeader('X-API-KEY', 'test-mobilipa-key')
                && $request['buyer_email'] === 'buyer@example.com'
                && $request['buyer_name'] === 'John Doe'
                && $request['buyer_phone'] === '255797455136'
                && $request['amount'] === 10000
                && $request['currency'] === 'TZS';
        });

        $this->assertDatabaseHas('transactions', [
            'transaction_id' => 'mp_69e9623649553',
            'user_id' => (string) $user->id,
            'user_email' => 'buyer@example.com',
            'type' => 'PURCHASE_CONNECTION',
            'zone' => 'connection',
            'status' => 'PENDING',
            'provider' => 'mobilipa',
            'payment_status' => 'PENDING',
            'reference' => 'M20515045387',
            'buyer_phone' => '255797455136',
        ]);
    }

    public function test_status_polling_marks_mobilipa_transaction_complete_and_unlocks_the_video(): void
    {
        $user = User::factory()->create([
            'email' => 'buyer@example.com',
            'unlocked_connection_videos' => [],
        ]);

        Transaction::create([
            'user_id' => (string) $user->id,
            'user_email' => $user->email,
            'amount' => 200,
            'currency' => 'TZS',
            'type' => 'PURCHASE_CONNECTION',
            'zone' => 'connection',
            'item_id' => '22',
            'item_title' => 'Polled Video',
            'transaction_id' => 'mp_69e96e149c41f',
            'status' => 'PENDING',
            'provider' => 'mobilipa',
            'payment_status' => 'PENDING',
        ]);

        Http::fake([
            'https://mobilipa.store/api/v1/order-status.php*' => Http::response([
                'success' => true,
                'data' => [
                    'order_id' => 'mp_69e96e149c41f',
                    'reference' => '1679319303',
                    'amount' => 200,
                    'currency' => 'TZS',
                    'status' => 'COMPLETED',
                    'type' => 'PAYMENT',
                    'payer_phone' => '255797455136',
                    'created_at' => '2026-04-23 00:55:52',
                    'transid' => 'DDNIN0NPJQ',
                    'channel' => 'MPESA-TZ',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->getJson('/api/payments/mobilipa/orders/mp_69e96e149c41f');

        $response
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'order_id' => 'mp_69e96e149c41f',
                    'payment_status' => 'COMPLETED',
                    'status' => 'COMPLETED',
                    'reference' => '1679319303',
                    'transid' => 'DDNIN0NPJQ',
                    'channel' => 'MPESA-TZ',
                    'msisdn' => '255797455136',
                    'access_granted' => true,
                ],
            ]);

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://mobilipa.store/api/v1/order-status.php')
                && $request->hasHeader('X-API-KEY', 'test-mobilipa-key')
                && $request['order_id'] === 'mp_69e96e149c41f';
        });

        $this->assertDatabaseHas('transactions', [
            'transaction_id' => 'mp_69e96e149c41f',
            'status' => 'COMPLETED',
            'payment_status' => 'COMPLETED',
            'provider_transaction_id' => 'DDNIN0NPJQ',
            'channel' => 'MPESA-TZ',
            'reference' => '1679319303',
            'msisdn' => '255797455136',
            'provider_event' => 'order_status.polled',
        ]);

        $this->assertContains('22', $user->fresh()->unlocked_connection_videos ?? []);
    }

    public function test_mobilipa_webhook_processes_successful_payment(): void
    {
        $user = User::factory()->create([
            'email' => 'webhook@example.com',
            'unlocked_connection_videos' => [],
        ]);

        Transaction::create([
            'user_id' => (string) $user->id,
            'user_email' => $user->email,
            'amount' => 5000,
            'currency' => 'TZS',
            'type' => 'PURCHASE_CONNECTION',
            'zone' => 'connection',
            'item_id' => '99',
            'item_title' => 'Webhook Video',
            'transaction_id' => 'mp_webhook_123',
            'status' => 'PENDING',
            'provider' => 'mobilipa',
            'payment_status' => 'PENDING',
        ]);

        $payload = json_encode([
            'order_id' => 'mp_webhook_123',
            'status' => 'SUCCESS',
            'payment_status' => 'SUCCESS',
            'reference' => 'REF_WH_999',
            'transid' => 'TX_MOB_999',
            'amount' => 5000,
            'currency' => 'TZS',
            'channel' => 'AIRTEL-TZ',
            'msisdn' => '255688123456',
        ], JSON_THROW_ON_ERROR);

        $signature = hash_hmac('sha256', $payload, 'test-mobilipa-webhook-secret');

        $response = $this->call(
            'POST',
            '/api/payments/mobilipa/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_MOBILIPA_SIGNATURE' => $signature,
            ],
            $payload
        );

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
            ]);

        $this->assertDatabaseHas('transactions', [
            'transaction_id' => 'mp_webhook_123',
            'status' => 'COMPLETED',
            'payment_status' => 'SUCCESS',
            'provider_transaction_id' => 'TX_MOB_999',
            'channel' => 'AIRTEL-TZ',
        ]);

        $this->assertContains('99', $user->fresh()->unlocked_connection_videos ?? []);
    }
}
