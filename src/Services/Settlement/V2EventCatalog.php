<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Settlement;

final class V2EventCatalog
{
    /** @return array<string, array{label: string, stage: string, workflows: list<string>, requires_destination?: bool}> */
    public function definitions(): array
    {
        return [
            'attestation.evidence_captured' => ['label' => 'Attestation recorded', 'stage' => 'attest', 'workflows' => ['philhealth']],
            'settlement.amount_reviewed' => ['label' => 'Settlement amount reviewed', 'stage' => 'review', 'workflows' => ['philhealth']],
            'settlement.ready' => ['label' => 'Settlement ready', 'stage' => 'review', 'workflows' => ['philhealth']],
            'settlement.payment_collected' => ['label' => 'Payment confirmed', 'stage' => 'settle', 'workflows' => ['philhealth', 'payable']],
            'settlement.payout_confirmed' => ['label' => 'Payout confirmed', 'stage' => 'settle', 'workflows' => ['philhealth', 'payable'], 'requires_destination' => true],
            'settlement.payout_failed' => ['label' => 'Payout failed', 'stage' => 'settle', 'workflows' => ['philhealth', 'payable'], 'requires_destination' => true],
        ];
    }
}
