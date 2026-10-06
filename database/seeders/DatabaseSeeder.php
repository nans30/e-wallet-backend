<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\WalletService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $walletService = app(WalletService::class);

        // 1. Akun Pengguna Pertama (Alice)
        $alice = User::firstOrCreate(
            ['email' => 'alice@fintech.test'],
            [
                'name' => 'Alice Margatroid',
                'password' => Hash::make('password123'),
            ]
        );
        $walletService->getOrCreateWallet($alice);
        $walletService->topUp($alice, 2500000, 'SEED-ALICE-1', 'Deposit Awal Alice');

        // 2. Akun Pengguna Kedua (Bob)
        $bob = User::firstOrCreate(
            ['email' => 'bob@fintech.test'],
            [
                'name' => 'Bob Smith',
                'password' => Hash::make('password123'),
            ]
        );
        $walletService->getOrCreateWallet($bob);
        $walletService->topUp($bob, 1000000, 'SEED-BOB-1', 'Deposit Awal Bob');

        // 3. Simulasi 1 kali Transfer Alice -> Bob
        $walletService->transfer(
            $alice,
            $bob->email,
            250000,
            'SEED-TX-1',
            'Pembayaran Invoice Freelance #101'
        );
    }
}
