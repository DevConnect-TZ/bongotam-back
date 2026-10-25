<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('subscriptions:audit-forged
    {--email= : Only audit the user with this email}
    {--apply : Revoke the forged months (default is a dry run)}')]
#[Description('Detect Wakubwa months granted by the removed unverified subscribe endpoint and revoke them')]
class AuditForgedSubscriptions extends Command
{
    public function handle(): int
    {
        $forged = Transaction::query()
            ->where('type', 'WAKUBWA_SUBSCRIPTION')
            ->where('status', 'COMPLETED')
            ->whereNull('provider')
            ->when($this->option('email'), fn ($query, $email) => $query->where('user_email', strtolower((string) $email)))
            ->get()
            ->groupBy('user_id');

        if ($forged->isEmpty()) {
            $this->info('No forged Wakubwa subscription records found.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($forged as $userId => $transactions) {
            $user = User::find((int) $userId);

            if (! $user) {
                $rows[] = [$userId, $transactions->first()->user_email ?? 'unknown', $transactions->count(), 'missing user', 'skipped'];

                continue;
            }

            $current = $user->wakubwa_subscription_expires_at;
            $forgedMonths = $transactions->count();
            $newExpiry = $current?->copy()->subMonths($forgedMonths);

            if ($newExpiry?->isPast()) {
                $newExpiry = null;
            }

            $rows[] = [
                $user->id,
                $user->email,
                $forgedMonths,
                $current?->toDateTimeString() ?? 'none',
                $newExpiry?->toDateTimeString() ?? 'none',
            ];

            if ($this->option('apply')) {
                $user->forceFill(['wakubwa_subscription_expires_at' => $newExpiry])->save();

                Transaction::whereKey($transactions->modelKeys())->update(['status' => 'REVOKED']);
            }
        }

        $this->table(['User ID', 'Email', 'Forged months', 'Current expiry', 'New expiry'], $rows);

        if ($this->option('apply')) {
            $this->info('Revoked forged months and marked the forged transactions as REVOKED.');
        } else {
            $this->warn('Dry run. Re-run with --apply to revoke the forged months.');
        }

        return self::SUCCESS;
    }
}
