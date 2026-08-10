<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Claim;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\PreparedCompiledClaimData;
use LBHurtado\XChange\Services\SettlementRailResolver;
use LBHurtado\XChange\Support\Claim\PayoutDestinationRegistry;

final class BuildCompiledFormClaimPayload
{
    public function __construct(
        protected PayoutDestinationRegistry $destinations,
        protected SettlementRailResolver $settlementRails,
    ) {}

    public function handle(
        Voucher $voucher,
        PreparedCompiledClaimData $prepared,
    ): array {
        $inputs = $prepared->inputs;
        $claimInputs = $inputs;
        $settlementRail = $this->settlementRails->forVoucher(
            $voucher,
            (float) ($inputs['amount'] ?? data_get($voucher->instructions, 'cash.amount', 0)),
        )->value;
        $destination = $this->destinations->snapshot(
            $inputs['bank_code'] ?? null,
            $inputs['account_number'] ?? null,
            $settlementRail,
        );

        unset($claimInputs['amount'], $claimInputs['settlement_rail'], $claimInputs['slice_ids'], $claimInputs['secret']);

        return [
            'source' => 'compiled_form',
            'code' => $prepared->code,
            'voucher_id' => $prepared->voucherId,
            'secret' => $inputs['secret'] ?? null,
            'mobile' => $inputs['mobile'] ?? null,
            'country' => $inputs['recipient_country'] ?? $inputs['country'] ?? 'PH',
            'bank_code' => $inputs['bank_code'] ?? null,
            'account_number' => $inputs['account_number'] ?? null,
            'amount' => $inputs['amount'] ?? null,
            'slice_ids' => $inputs['slice_ids'] ?? [],
            'settlement_rail' => $settlementRail,
            'destination' => $destination,
            'inputs' => $claimInputs,
        ];
    }
}
