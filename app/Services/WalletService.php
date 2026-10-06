<?php

namespace App\Services;

use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Exception;
use Illuminate\Support\Facades\DB;

class WalletService
{
    /**
     * Get or create user wallet
     */
    public function getOrCreateWallet(User $user, string $currency = 'IDR'): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $user->id, 'currency' => $currency],
            ['balance' => 0]
        );
    }

    /**
     * Top-up balance
     */
    public function topUp(User $user, int $amount, string $referenceId, ?string $description = null): Transaction
    {
        if ($amount <= 0) {
            throw new Exception("Nominal top up harus lebih besar dari 0.");
        }

        return DB::transaction(function () use ($user, $amount, $referenceId, $description) {
            // Lock the user's wallet
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();

            if (!$wallet) {
                $wallet = Wallet::create([
                    'user_id' => $user->id,
                    'currency' => 'IDR',
                    'balance' => 0,
                ]);
                $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();
            }

            // Create Transaction
            $transaction = Transaction::create([
                'reference_id' => $referenceId,
                'user_id' => $user->id,
                'type' => 'TOPUP',
                'amount' => $amount,
                'status' => 'SUCCESS',
                'description' => $description ?? "Top Up Saldo Sebesar Rp " . number_format($amount, 0, ',', '.'),
            ]);

            // Ledger Entry (CREDIT = masuk)
            $balanceBefore = $wallet->balance;
            $wallet->increment('balance', $amount);
            $wallet->refresh();

            Ledger::create([
                'transaction_id' => $transaction->id,
                'wallet_id' => $wallet->id,
                'entry_type' => 'CREDIT',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $wallet->balance,
            ]);

            return $transaction;
        }, 5);
    }

    /**
     * Transfer between users with Pessimistic Locking & Deadlock prevention
     */
    public function transfer(User $sender, string $recipientEmail, int $amount, string $referenceId, ?string $description = null): Transaction
    {
        if ($amount <= 0) {
            throw new Exception("Nominal transfer harus lebih besar dari 0.");
        }

        $recipient = User::where('email', $recipientEmail)->first();
        if (!$recipient) {
            throw new Exception("Penerima dengan email tersebut tidak ditemukan.");
        }

        if ($sender->id === $recipient->id) {
            throw new Exception("Tidak dapat mentransfer ke akun sendiri.");
        }

        return DB::transaction(function () use ($sender, $recipient, $amount, $referenceId, $description) {
            // Pastikan wallet sudah ada untuk keduanya
            $this->getOrCreateWallet($sender);
            $this->getOrCreateWallet($recipient);

            // Urutkan user IDs untuk mencegah Deadlock saat locking bersamaan
            $userIds = [$sender->id, $recipient->id];
            sort($userIds);

            $wallets = Wallet::whereIn('user_id', $userIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('user_id');

            $senderWallet = $wallets->get($sender->id);
            $recipientWallet = $wallets->get($recipient->id);

            if (!$senderWallet || $senderWallet->balance < $amount) {
                throw new Exception("Saldo tidak mencukupi untuk melakukan transfer ini.");
            }

            // 1. Transaction Record
            $transaction = Transaction::create([
                'reference_id' => $referenceId,
                'user_id' => $sender->id,
                'type' => 'TRANSFER',
                'amount' => $amount,
                'status' => 'SUCCESS',
                'description' => $description ?? "Transfer ke {$recipient->name} ({$recipient->email})",
            ]);

            // 2. Sender Ledger (DEBIT)
            $senderBefore = $senderWallet->balance;
            $senderWallet->decrement('balance', $amount);
            $senderWallet->refresh();

            Ledger::create([
                'transaction_id' => $transaction->id,
                'wallet_id' => $senderWallet->id,
                'entry_type' => 'DEBIT',
                'amount' => $amount,
                'balance_before' => $senderBefore,
                'balance_after' => $senderWallet->balance,
            ]);

            // 3. Recipient Ledger (CREDIT)
            $recipientBefore = $recipientWallet->balance;
            $recipientWallet->increment('balance', $amount);
            $recipientWallet->refresh();

            Ledger::create([
                'transaction_id' => $transaction->id,
                'wallet_id' => $recipientWallet->id,
                'entry_type' => 'CREDIT',
                'amount' => $amount,
                'balance_before' => $recipientBefore,
                'balance_after' => $recipientWallet->balance,
            ]);

            return $transaction;
        }, 5);
    }
}
