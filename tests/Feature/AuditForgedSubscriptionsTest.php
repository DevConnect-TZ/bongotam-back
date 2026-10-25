<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditForgedSubscriptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_without_changing_anything(): void
    {
        $user = User::factory()->create(['wakubwa_subscription_expires_at' => now()->addMonths(6)]);
        $expiry = $user->fresh()->wakubwa_subscription_expires_at;
        $this->forged($user, 5);

        $this->artisan('subscriptions:audit-forged')
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertTrue($user->fresh()->wakubwa_subscription_expires_at->equalTo($expiry));
        $this->assertSame(5, Transaction::where('status', 'COMPLETED')->count());
    }

    public function test_apply_removes_only_forged_months_and_marks_records(): void
    {
        $user = User::factory()->create(['wakubwa_subscription_expires_at' => now()->addMonths(8)]);
        $this->forged($user, 5);

        Transaction::create([
            'user_id' => (string) $user->id,
            'user_email' => $user->email,
            'amount' => 3000,
            'currency' => 'TZS',
            'zone' => 'wakubwa',
            'type' => 'PURCHASE_WAKUBWA',
            'transaction_id' => 'legit-1',
            'provider' => 'mobilipa',
            'status' => 'COMPLETED',
        ]);

        $this->artisan('subscriptions:audit-forged --apply')->assertSuccessful();

        $expiry = $user->fresh()->wakubwa_subscription_expires_at;
        $this->assertNotNull($expiry);
        $this->assertTrue($expiry->between(now()->addMonths(3)->subDay(), now()->addMonths(3)->addDay()));
        $this->assertSame(0, Transaction::where('type', 'WAKUBWA_SUBSCRIPTION')->where('status', 'COMPLETED')->count());
        $this->assertSame(5, Transaction::where('status', 'REVOKED')->count());
        $this->assertSame(1, Transaction::where('type', 'PURCHASE_WAKUBWA')->where('status', 'COMPLETED')->count());
    }

    public function test_apply_clears_expiry_when_nothing_valid_remains(): void
    {
        $user = User::factory()->create(['wakubwa_subscription_expires_at' => now()->addMonths(3)]);
        $this->forged($user, 5);

        $this->artisan('subscriptions:audit-forged --apply')->assertSuccessful();

        $this->assertNull($user->fresh()->wakubwa_subscription_expires_at);
        $this->assertFalse($user->fresh()->isWakubwaSubscribed());
    }

    public function test_email_option_only_touches_the_selected_user(): void
    {
        $target = User::factory()->create(['wakubwa_subscription_expires_at' => now()->addMonths(6)]);
        $other = User::factory()->create(['wakubwa_subscription_expires_at' => now()->addMonths(6)]);
        $this->forged($target, 7);
        $this->forged($other, 5);

        $this->artisan("subscriptions:audit-forged --email={$target->email} --apply")->assertSuccessful();

        $this->assertFalse($target->fresh()->isWakubwaSubscribed());
        $this->assertTrue($other->fresh()->isWakubwaSubscribed());
    }

    private function forged(User $user, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            Transaction::create([
                'user_id' => (string) $user->id,
                'user_email' => $user->email,
                'amount' => 1,
                'currency' => 'TSHS',
                'type' => 'WAKUBWA_SUBSCRIPTION',
                'transaction_id' => 'forged-'.$user->id.'-'.$i,
                'status' => 'COMPLETED',
            ]);
        }
    }
}
