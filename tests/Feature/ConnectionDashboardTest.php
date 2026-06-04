<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectionDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is required for database-backed connection dashboard tests.');
        }

        parent::setUp();
    }

    public function test_the_connection_dashboard_route_renders_revenue_and_registration_sections(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'role' => 'admin',
            'status' => 'active',
        ]);

        User::factory()->create([
            'email' => 'member@example.com',
            'role' => 'user',
            'status' => 'active',
        ]);

        Transaction::create([
            'user_id' => '1',
            'user_email' => 'member@example.com',
            'amount' => 15000,
            'currency' => 'TZS',
            'type' => 'PURCHASE_CONNECTION',
            'zone' => 'connection',
            'item_id' => '8',
            'item_title' => 'Analytics Test Video',
            'transaction_id' => 'sp_test_connection',
            'status' => 'COMPLETED',
            'provider' => 'sonicpesa',
            'payment_status' => 'SUCCESS',
            'reference' => 'REF123',
        ]);

        $response = $this->withSession([
            'connection_admin_id' => $admin->id,
            'connection_admin_email' => $admin->email,
        ])->get('/connection');

        $response
            ->assertOk()
            ->assertSee('Connection Admin Analytics')
            ->assertSee('Revenue trend')
            ->assertSee('Registration trend')
            ->assertSee('Existing admin roster')
            ->assertSee('TZS 15,000');
    }
}
