<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidatePaymentGatewayHmac
{
    /**
     * Memvalidasi signature HMAC-SHA256 dari payload Webhook Payment Gateway.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $signature = $request->header('X-Signature');
        $secretKey = config('services.payment_gateway.webhook_secret', env('PG_WEBHOOK_SECRET', 'fintech_secret_key'));

        if (!$signature) {
            return response()->json([
                'status' => 'error',
                'message' => 'Header X-Signature tidak ditemukan.'
            ], 401);
        }

        // Payload RAW wajib digunakan untuk HMAC hashing
        $rawPayload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $rawPayload, $secretKey);

        // Mitigasi Timing Attack menggunakan hash_equals
        if (!hash_equals($expectedSignature, $signature)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Signature HMAC tidak valid atau payload telah dimanipulasi.'
            ], 403);
        }

        return $next($request);
    }
}
