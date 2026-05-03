<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Settlement;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;

class SubmitSettlementAttestation
{
    public function __construct(
        protected SubmitPayCodeClaim $submitPayCodeClaim,
    ) {}

    public function handle(Voucher $voucher, array $payload): SubmitPayCodeClaimResultData
    {
        return $this->submitPayCodeClaim->handle($voucher, [
            ...$payload,
            'claim_type' => $payload['claim_type'] ?? 'redeem',
            'settlement_attestation' => true,
        ]);
    }
}
