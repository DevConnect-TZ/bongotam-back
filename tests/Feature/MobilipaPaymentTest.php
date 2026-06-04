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
            'services.mobilipa.base_url' => 'https://api.mobilipa.store',
            'services.mobilipa.api_key' => 'test-mobilipa-key',
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
            'https://api.mobilipa.store/*' => Http::response([
                'status' => 'success',
                'message' => 'Payment order created successfully! Push USSD sent to your phone.',
                'data' => [
                    'order_id' => 'mp_69e9623649553',
                    'reference' => 'M20515045387',
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
            return $request->url() === 'https://api.mobilipa.store/v1/payment/create_order'
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
            'https://api.mobilipa.store/v1/payment/status' => Http::response([
                'status' => 'success',
                'message' => 'Order status retrieved successfully',
                'data' => [
                    'order_id' => 'mp_69e96e149c41f',
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
                    'order_id' => 'mp_69e96e149c41f',
                    'status' => 'SUCCESS',
                    'amount' => '200',
                    'buyer_email' => 'buyer@example.com',
                    'buyer_name' => 'John Doe',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->getJson('/api/payments/sonicpesa/orders/mp_69e96e149c41f');

        $response
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'order_id' => 'mp_69e96e149c41f',
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
            return $request->url() === 'https://api.mobilipa.store/v1/payment/status'
                && $request->hasHeader('X-API-KEY', 'test-mobilipa-key')
                && $request['order_id'] === 'mp_69e96e149c41f';
        });

        $this->assertDatabaseHas('transactions', [
            'transaction_id' => 'mp_69e96e149c41f',
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
