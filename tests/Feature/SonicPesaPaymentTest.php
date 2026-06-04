<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthenticateApiAccessToken;
use App\Http\Middleware\EnsureApiAdmin;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SonicPesaPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is required for database-backed SonicPesa payment tests.');
        }

        parent::setUp();

        config([
            'services.sonicpesa.base_url' => 'https://api.sonicpesa.com',
            'services.sonicpesa.api_key' => 'test-api-key',
            'services.sonicpesa.api_secret' => 'test-api-secret',
        ]);

        $this->withoutMiddleware([
            AuthenticateApiAccessToken::class,
            EnsureApiAdmin::class,
        ]);
    }

    public function test_it_creates_a_sonicpesa_order_and_persists_the_pending_transaction(): void
    {
        $user = User::factory()->create([
            'email' => 'buyer@example.com',
        ]);

        Http::fake([
            'https://api.sonicpesa.com/*' => Http::response([
                'status' => 'success',
                'message' => 'Payment order created successfully! Push USSD sent to your phone.',
                'data' => [
                    'order_id' => 'sp_69e9623649553',
                    'reference' => 'S20515045387',
                    'amount' => 10000,
                    'currency' => 'TZS',
                    'payment_status' => 'PENDING',
                    'status' => 'PENDING',
                    'creation_date' => '2026-04-23 03:06:13',
                    'transid' => null,
                    'channel' => null,
                    'msisdn' => '255797455136',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->postJson('/api/payments/sonicpesa/order', [
            'user_email' => $user->email,
            'buyer_name' => 'John Doe',
            'buyer_phone' => '0797455136',
            'amount' => 10000,
            'currency' => 'TZS',
            'type' => 'PURCHASE_CONNECTION',
            'item_id' => '17',
            'item_title' => 'Premium Connection Video',
            'zone' => 'connection',
        ]);

        $response
            ->assertCreated()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'order_id' => 'sp_69e9623649553',
                    'reference' => 'S20515045387',
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
            return $request->url() === 'https://api.sonicpesa.com/api/v1/payment/create_order'
                && $request->hasHeader('X-API-KEY', 'test-api-key')
                && $request['buyer_email'] === 'buyer@example.com'
                && $request['buyer_name'] === 'John Doe'
                && $request['buyer_phone'] === '255797455136'
                && $request['amount'] === 10000
                && $request['currency'] === 'TZS';
        });

        $this->assertDatabaseHas('transactions', [
            'transaction_id' => 'sp_69e9623649553',
            'user_id' => (string) $user->id,
            'user_email' => 'buyer@example.com',
            'type' => 'PURCHASE_CONNECTION',
            'zone' => 'connection',
            'status' => 'PENDING',
            'provider' => 'sonicpesa',
            'payment_status' => 'PENDING',
            'reference' => 'S20515045387',
            'buyer_phone' => '255797455136',
        ]);
    }

    public function test_webhook_marks_transaction_complete_and_unlocks_the_video(): void
    {
        $user = User::factory()->create([
            'email' => 'buyer@example.com',
            'unlocked_connection_videos' => [],
        ]);

        Transaction::create([
            'user_id' => (string) $user->id,
            'user_email' => $user->email,
            'amount' => 10000,
            'currency' => 'TZS',
            'type' => 'PURCHASE_CONNECTION',
            'zone' => 'connection',
            'item_id' => '17',
            'item_title' => 'Premium Connection Video',
            'transaction_id' => 'sp_67890abcdef',
            'status' => 'PENDING',
            'provider' => 'sonicpesa',
            'payment_status' => 'PENDING',
        ]);

        $payload = [
            'event' => 'payment.completed',
            'order_id' => 'sp_67890abcdef',
            'amount' => 10000,
            'currency' => 'TZS',
            'status' => 'SUCCESS',
            'transid' => 'TXN123456',
            'channel' => 'AIRTELMONEY',
            'reference' => '0289999288',
            'msisdn' => '255682812345',
            'timestamp' => '2025-01-07T12:05:00Z',
        ];

        $payloadRaw = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $payloadRaw, 'test-api-secret');

        $response = $this->call(
            'POST',
            '/api/payments/sonicpesa/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SONICPESA_SIGNATURE' => $signature,
            ],
            $payloadRaw
        );

        $response
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'Webhook received.',
            ]);

        $this->assertDatabaseHas('transactions', [
            'transaction_id' => 'sp_67890abcdef',
            'status' => 'COMPLETED',
            'payment_status' => 'SUCCESS',
            'provider_transaction_id' => 'TXN123456',
            'channel' => 'AIRTELMONEY',
            'reference' => '0289999288',
            'msisdn' => '255682812345',
            'provider_event' => 'payment.completed',
        ]);

        $this->assertContains('17', $user->fresh()->unlocked_connection_videos ?? []);
    }

    public function test_status_polling_marks_transaction_complete_and_unlocks_the_video(): void
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
            'transaction_id' => 'sp_69e96e149c41f',
            'status' => 'PENDING',
            'provider' => 'sonicpesa',
            'payment_status' => 'PENDING',
        ]);

        Http::fake([
            'https://api.sonicpesa.com/api/v1/payment/order_status' => Http::response([
                'status' => 'success',
                'message' => 'Order status retrieved successfully',
                'data' => [
                    'order_id' => 'sp_69e96e149c41f',
                    'payment_status' => 'SUCCESS',
                    'amount' => 200,
                    'currency' => 'TZS',
                    'phone' => '255797455136',
                    'transid' => 'DDNIN0NPJQ',
                    'reference' => '1679319303',
                    'channel' => 'MPESA-TZ',
                    'msisdn' => '255797455136',
                    'created_at' => '2026-04-23T00:55:52.000000Z',
                    'cached' => true,
                ],
                'transaction' => [
                    'order_id' => 'sp_69e96e149c41f',
                    'status' => 'SUCCESS',
                    'amount' => '200',
                    'buyer_email' => 'buyer@example.com',
                    'buyer_name' => 'John Doe',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->getJson('/api/payments/sonicpesa/orders/sp_69e96e149c41f');

        $response
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'order_id' => 'sp_69e96e149c41f',
                    'payment_status' => 'SUCCESS',
                    'status' => 'COMPLETED',
                    'reference' => '1679319303',
                    'transid' => 'DDNIN0NPJQ',
                    'channel' => 'MPESA-TZ',
                    'msisdn' => '255797455136',
                    'access_granted' => true,
                ],
            ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.sonicpesa.com/api/v1/payment/order_status'
                && $request->hasHeader('X-API-KEY', 'test-api-key')
                && $request['order_id'] === 'sp_69e96e149c41f';
        });

        $this->assertDatabaseHas('transactions', [
            'transaction_id' => 'sp_69e96e149c41f',
            'status' => 'COMPLETED',
            'payment_status' => 'SUCCESS',
            'provider_transaction_id' => 'DDNIN0NPJQ',
            'channel' => 'MPESA-TZ',
            'reference' => '1679319303',
            'msisdn' => '255797455136',
            'provider_event' => 'order_status.polled',
        ]);

        $this->assertContains('22', $user->fresh()->unlocked_connection_videos ?? []);
    }
}
