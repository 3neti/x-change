<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Reserve;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\ReserveTransfer;

final class ReserveIssuanceStatusPresenter
{
    /** @return array{reference: string, status: string, amount_minor: int, currency: string, pay_code: ?string, status_url: string, detail_url: ?string} */
    public function present(ReserveTransfer $transfer, ?string $knownCode = null, bool $codeLoaded = false): array
    {
        $status = match ($transfer->status) {
            'prepared', 'held', 'submitting', 'pending', 'provider_settled', 'treasury_posted' => 'processing',
            'uncertain', 'review_required' => 'needs_review',
            'rejected', 'released' => 'failed',
            'issued' => 'issued',
            default => 'needs_review',
        };
        $code = $status === 'issued' && $transfer->voucher_id !== null
            ? ($codeLoaded ? $knownCode : Voucher::query()->whereKey($transfer->voucher_id)->value('code')) : null;
        if ($status === 'issued' && ! is_string($code)) {
            $status = 'needs_review';
        }

        return [
            'reference' => $transfer->reference, 'status' => $status,
            'amount_minor' => $transfer->amount_minor, 'currency' => $transfer->currency,
            'pay_code' => $status === 'issued' ? $code : null,
            'status_url' => route('x-change.cockpit.quick-generate.reserve-issuances.show', ['reference' => $transfer->reference], false),
            'detail_url' => $status === 'issued' ? route('x-change.cockpit.pay-codes.show', ['code' => $code], false) : null,
        ];
    }
}
