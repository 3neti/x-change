<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Settlement;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use LBHurtado\XChange\Services\SettlementEnvelopeMetadataSyncService;

class SubmitSettlementAttestation
{
    public function __construct(
        protected SubmitPayCodeClaim $submitPayCodeClaim,
        protected SettlementEnvelopeMetadataSyncService $envelopeSync,
    ) {}

    public function handle(Voucher $voucher, array $payload): SubmitPayCodeClaimResultData
    {
        $payload = [
            ...$payload,
            'claim_type' => $payload['claim_type'] ?? 'redeem',
            'settlement_attestation' => true,
        ];

        $result = $this->submitPayCodeClaim->handle($voucher, $payload);

        $this->envelopeSync->syncPatientAttestation($voucher->refresh(), $payload);

        return $result;
    }
}
