<?php

namespace App\Console\Commands;

use App\Models\WakubwaSubscription;
use Illuminate\Console\Command;

class SyncSubscribersCommand extends Command
{
    protected $signature = 'subscribers:sync-status';
    protected $description = 'Sync and expire past Wakubwa Zone subscriptions';

    public function handle(): int
    {
        $expired = WakubwaSubscription::where('expires_at', '<', now())
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $this->info("Deactivated {$expired} expired subscriptions.");
        return Command::SUCCESS;
    }
}
