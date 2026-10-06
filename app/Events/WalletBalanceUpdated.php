<?php

namespace App\Events;

use App\Models\Wallet;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WalletBalanceUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Wallet $wallet, public array $payload = [])
    {
    }

    /**
     * Private channel per user (e.g. App.Models.User.1)
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->wallet->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'wallet.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'wallet_id' => $this->wallet->id,
            'balance' => $this->wallet->balance,
            'currency' => $this->wallet->currency,
            'last_event' => $this->payload,
            'timestamp' => now()->toISOString(),
        ];
    }
}
