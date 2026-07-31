<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Redemption;

use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use LBHurtado\XChange\Jobs\Redemption\DispatchVoucherRedemptionFeedbackJob;
use LBHurtado\XChange\Models\VoucherClaim;
use Throwable;

final class QueueVoucherRedemptionFeedback
{
    public function handle(
        VoucherClaim $claim,
        SubmitPayCodeClaimResultData $result,
    ): void {
        if (
            ! (bool) config('x-change.redemption.feedback.enabled', true)
            || ! $result->claimed
            || ! in_array(
                $claim->status,
                (array) config('x-change.redemption.feedback.terminal_claim_statuses', []),
                true,
            )
            || ! $this->hasRoutes($claim)
        ) {
            return;
        }

        try {
            DispatchVoucherRedemptionFeedbackJob::dispatch($claim->getKey())
                ->afterCommit();
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function hasRoutes(VoucherClaim $claim): bool
    {
        $feedback = data_get($claim->voucher->metadata, 'instructions.feedback');

        if (! is_array($feedback)) {
            $feedback = $claim->voucher->instructions->feedback->toArray();
        }

        foreach (['email', 'mobile', 'webhook'] as $key) {
            if (
                is_string($feedback[$key] ?? null)
                && trim((string) $feedback[$key]) !== ''
            ) {
                return true;
            }
        }

        return false;
    }
}
