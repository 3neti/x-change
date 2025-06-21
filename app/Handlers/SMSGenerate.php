<?php

namespace App\Handlers;

use LBHurtado\OmniChannel\Contracts\SMSHandlerInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use App\Actions\CutCheck;
use App\Models\User;

class SMSGenerate implements SMSHandlerInterface
{
    protected string $action = 'Generate';

    public function __invoke(array $values, string $from, string $to): JsonResponse
    {
        Log::info('[SMSGenerate] Starting', compact('values', 'from', 'to'));

        $instruction = Arr::get($values, 'extra', '');
        Log::debug('[SMSGenerate] Instruction text', ['instruction' => $instruction]);

        // Find & authenticate the user
        $user = User::findByMobile($from);
        if (! $user) {
            Log::warning('[SMSGenerate] No user for mobile', ['from' => $from]);
            return response()->json([
                'error' => 'Could not find an account for this number.',
            ], 404);
        }
        Auth::setUser($user);

        // Run the CutCheck action
        try {
            $vouchers = CutCheck::run($this->action . $instruction);
            Log::info('[SMSGenerate] Generated vouchers count', ['count' => $vouchers->count()]);
        } catch (\Throwable $e) {
            Log::error('[SMSGenerate] Error generating vouchers', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Failed to generate vouchers. Please try again later.',
            ], 500);
        }

        if ($vouchers->isEmpty()) {
            Log::info('[SMSGenerate] No vouchers generated');
            return response()->json([
                'message' => 'ℹ️ No vouchers were generated.',
            ]);
        }

        // build a comma‐delimited list of codes
        $codes   = $vouchers->pluck('code')->implode(', ');
        // assume all vouchers share same amount & expiry
        $first   = $vouchers->first();
        $amount  = (string) $first->instructions->cash->getAmount();
        $expires = $first->expires_at?->toDateTimeString() ?? '—';

        $reply = sprintf(
            '🎉 Generated %d voucher(s): %s (%s, expiring on %s)',
            $vouchers->count(),
            $codes,
            $amount,
            $expires
        );

        Log::debug('[SMSGenerate] Reply prepared', ['reply' => $reply]);

        return response()->json([
            'message' => $reply,
        ]);
    }
}
