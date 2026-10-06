<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Services\WalletService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(protected WalletService $walletService) {}

    public function getWallet(Request $request): JsonResponse
    {
        $wallet = $this->walletService->getOrCreateWallet($request->user());

        return response()->json([
            'status' => 'success',
            'data' => $wallet,
        ]);
    }

    public function topUp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|integer|min:10000|max:50000000',
            'description' => 'nullable|string|max:255',
        ]);

        $referenceId = $request->header('X-Idempotency-Key') ?? 'TOPUP-' . uniqid();

        try {
            $transaction = $this->walletService->topUp(
                $request->user(),
                $validated['amount'],
                $referenceId,
                $validated['description'] ?? null
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Top up saldo berhasil!',
                'data' => $transaction,
                'wallet' => $this->walletService->getOrCreateWallet($request->user()),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function transfer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipient_email' => 'required|email',
            'amount' => 'required|integer|min:1000|max:100000000',
            'description' => 'nullable|string|max:255',
        ]);

        $referenceId = $request->header('X-Idempotency-Key') ?? 'TX-' . uniqid();

        try {
            $transaction = $this->walletService->transfer(
                $request->user(),
                $validated['recipient_email'],
                $validated['amount'],
                $referenceId,
                $validated['description'] ?? null
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Transfer dana berhasil!',
                'data' => $transaction,
                'wallet' => $this->walletService->getOrCreateWallet($request->user()),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function getTransactions(Request $request): JsonResponse
    {
        $wallet = $this->walletService->getOrCreateWallet($request->user());

        // Dapatkan ledger mutasi saldo pengguna saat ini
        $ledgers = Ledger::where('wallet_id', $wallet->id)
            ->with(['transaction.user'])
            ->latest()
            ->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $ledgers,
        ]);
    }
}
