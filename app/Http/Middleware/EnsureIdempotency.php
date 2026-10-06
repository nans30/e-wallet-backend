<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotency
{
    /**
     * Handle an incoming request with Idempotency Key validation and locking.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Hanya cek untuk method mutasi
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
            return $next($request);
        }

        $idempotencyKey = $request->header('X-Idempotency-Key');

        if (!$idempotencyKey) {
            return response()->json([
                'status' => 'error',
                'message' => 'Header X-Idempotency-Key diperlukan untuk transaksi ini.'
            ], 400);
        }

        $userId = $request->user()?->id ?? 'guest';
        $cacheKey = "idempotency:{$userId}:{$idempotencyKey}";
        $lockKey = "lock:{$cacheKey}";

        // Coba peroleh lock untuk mencegah request identik dieksekusi bersamaan
        $lock = Cache::lock($lockKey, 15);

        if (!$lock->get()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permintaan transaksi identik sedang diproses. Mohon tunggu.'
            ], 409);
        }

        try {
            // Jika response sudah pernah disimpan sebelumnya, kembalikan response cached
            if ($cachedResponse = Cache::get($cacheKey)) {
                return response()->json($cachedResponse['data'], $cachedResponse['status']);
            }

            $response = $next($request);

            // Simpan response bila sukses
            if ($response->isSuccessful()) {
                Cache::put($cacheKey, [
                    'data' => json_decode($response->getContent(), true),
                    'status' => $response->getStatusCode()
                ], now()->addHours(24));
            }

            return $response;
        } finally {
            $lock->release();
        }
    }
}
