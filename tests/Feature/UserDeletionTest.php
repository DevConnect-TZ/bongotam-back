<?php

namespace Tests\Feature;

use App\Models\AuthSession;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ApiSessionService;
use App\Services\FirebaseAdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use RuntimeException;
use Tests\TestCase;

class UserDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_permanently_delete_another_user_and_their_sessions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $session = AuthSession::create([
            'user_id' => $user->id,
            'refresh_token_hash' => hash('sha256', 'refresh-secret'),
            'refresh_expires_at' => now()->addDay(),
        ]);
        $transaction = Transaction::create([
            'user_id' => (string) $user->id,
            'user_email' => $user->email,
            'amount' => 3000,
            'currency' => 'TZS',
            'transaction_id' => 'retained-payment',
            'status' => 'COMPLETED',
        ]);

        $this->mock(FirebaseAdminService::class, function ($mock) use ($user): void {
            $mock->shouldReceive('isConfigured')->once()->andReturnTrue();
            $mock->shouldReceive('deleteUserByEmail')->once()->with($user->email);
        });

        $this->withToken($this->token($admin))
            ->deleteJson("/api/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('message', 'User account permanently deleted.');

        $this->assertModelMissing($user);
        $this->assertModelMissing($session);
        $this->assertModelExists($transaction);
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->mock(FirebaseAdminService::class, function ($mock): void {
            $mock->shouldNotReceive('isConfigured');
            $mock->shouldNotReceive('deleteUserByEmail');
        });

        $this->withToken($this->token($admin))
            ->deleteJson("/api/users/{$admin->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'You cannot permanently delete your own administrator account.');

        $this->assertModelExists($admin);
    }

    public function test_missing_firebase_admin_configuration_preserves_the_local_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $this->mock(FirebaseAdminService::class, function ($mock): void {
            $mock->shouldReceive('isConfigured')->once()->andReturnFalse();
            $mock->shouldNotReceive('deleteUserByEmail');
        });

        $this->withToken($this->token($admin))
            ->deleteJson("/api/users/{$user->id}")
            ->assertStatus(503);

        $this->assertModelExists($user);
    }

    public function test_firebase_failure_preserves_the_local_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $this->mock(FirebaseAdminService::class, function ($mock): void {
            $mock->shouldReceive('isConfigured')->once()->andReturnTrue();
            $mock->shouldReceive('deleteUserByEmail')->once()->andThrow(new RuntimeException('Firebase unavailable'));
        });

        $this->withToken($this->token($admin))
            ->deleteJson("/api/users/{$user->id}")
            ->assertStatus(502)
            ->assertJsonPath('message', 'The authentication account could not be deleted. No local data was removed.');

        $this->assertModelExists($user);
    }

    private function token(User $user): string
    {
        return app(ApiSessionService::class)->issueSession($user, Request::create('/'))['access_token'];
    }
}
