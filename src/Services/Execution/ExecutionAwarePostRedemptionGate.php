<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Execution;

use Closure;
use Illuminate\Support\Facades\Log;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Services\OnboardingVoucherInstructionPolicy;

final class ExecutionAwarePostRedemptionGate
{
    public function handle(Voucher $voucher, Closure $next): mixed
    {
        $mode = data_get(
            $voucher->metadata,
            'instructions.execution.metadata.post_redemption.mode',
        );

        if ($mode !== OnboardingVoucherInstructionPolicy::PostRedemptionMode) {
            return $next($voucher);
        }

        Log::info('[ExecutionAwarePostRedemptionGate] External payout suppressed by persisted execution policy.', [
            'voucher' => $voucher->code,
            'execution_driver' => data_get(
                $voucher->metadata,
                'instructions.execution.driver',
            ),
            'post_redemption_mode' => $mode,
        ]);

        return $voucher;
    }
}
