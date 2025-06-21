<?php

namespace App\Handlers;

use LBHurtado\OmniChannel\Contracts\SMSHandlerInterface;
use Propaganistas\LaravelPhone\PhoneNumber;
use LBHurtado\Voucher\Models\Voucher;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use App\Actions\EncashCheck;
use Illuminate\Support\Arr;

class SMSRedeem implements SMSHandlerInterface
{
    public function __invoke(array $values, string $from, string $to): JsonResponse
    {
        Log::info('[SMSRedeem] Starting', compact('values', 'from', 'to'));

        // 1️⃣ Voucher code
        $code = Arr::get($values, 'voucher');
        if (! is_string($code) || $code === '') {
            return response()->json([
                'error' => 'Usage: REDEEM {voucher_code} [bank_code:account_number]'
            ], 422);
        }

        // 2️⃣ Optional bank-account metadata
        $meta = [];
        $raw = Arr::get($values, 'extra');
        if (is_string($raw) && str_contains($raw, ':')) {
            $meta['bank_account'] = $raw;
        }

        // 3️⃣ Find the voucher
        $voucher = Voucher::where('code', $code)->first();
        if (! $voucher) {
            return response()->json([
                'message' => "❌ Voucher “{$code}” not found."
            ], 404);
        }

        // 4️⃣ Attempt redemption
        try {
            $success = EncashCheck::run(
                $voucher,
                new PhoneNumber(number: $from, country: config('omnichannel.default_country', 'PH')),
                $meta
            );
        } catch (\Throwable $e) {
            Log::error('[SMSRedeem] EncashCheck error', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => "⚠️ Failed to redeem. Please try again later."
            ], 500);
        }

        if (! $success) {
            return response()->json([
                'message' => "❌ Could not redeem voucher “{$code}”. It may be invalid or already redeemed."
            ], 422);
        }

        // 5️⃣ On success, reply with code, amount & expiration
        $amount  = (string) $voucher->instructions->cash->getAmount();
        $expires = $voucher->expires_at?->toDateTimeString() ?? '—';
        $reply   = "✅ Redeemed {$code} ({$amount}, expires {$expires})";

        return response()->json(['message' => $reply]);
    }
}
