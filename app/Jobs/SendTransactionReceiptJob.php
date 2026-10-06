<?php

namespace App\Jobs;

use App\Models\Transaction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendTransactionReceiptJob implements ShouldQueue
{
    use Queueable;

    // Retry configuration
    public int $tries = 3;
    public int $backoff = 10; // detik

    public function __construct(public Transaction $transaction)
    {
        $this->onQueue('receipts');
    }

    public function handle(): void
    {
        $tx = $this->transaction->load(['user', 'ledgers.wallet']);

        Log::info("📨 [Receipt Queue] Struk transaksi berhasil diproses untuk Tx Ref: {$tx->reference_id}", [
            'transaction_id' => $tx->id,
            'user' => $tx->user->email,
            'amount' => $tx->amount,
            'status' => $tx->status,
        ]);

        // Simulasi pengiriman email PDF Receipt atau Push Notification
    }
}
