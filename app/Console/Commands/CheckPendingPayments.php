<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\MobilipaService;
use App\Services\SonicPesaService;
use Illuminate\Console\Command;

class CheckPendingPayments extends Command
{
    protected $signature = 'payments:check-pending';
    protected $description = 'Poll status of pending transactions from active gateways';

    public function handle(MobilipaService $mobilipa, SonicPesaService $sonicPesa): int
    {
        $pending = Transaction::where('status', 'pending')
            ->where('created_at', '>=', now()->subHours(2))
            ->get();

        $this->info("Found {$pending->count()} pending transactions.");

        foreach ($pending as $tx) {
            $this->line("Checking tx: {$tx->order_id} ({$tx->payment_method})");
            if ($tx->payment_method === 'mobilipa') {
                $mobilipa->checkTransactionStatus($tx->order_id);
            }
        }

        return Command::SUCCESS;
    }
}
