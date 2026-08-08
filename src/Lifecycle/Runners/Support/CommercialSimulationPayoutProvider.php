<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners\Support;

use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Data\PayoutResultData;
use LBHurtado\EmiCore\Enums\PayoutStatus;
use LBHurtado\EmiCore\Enums\SettlementRail;

final class CommercialSimulationPayoutProvider implements PayoutProvider
{
    public int $disbursementCalls = 0;

    public int $statusCalls = 0;

    public function disburse(PayoutRequestData $request): PayoutResultData
    {
        $this->disbursementCalls++;

        return new PayoutResultData(
            transaction_id: 'SIMULATED-COMMISSION-'.substr(hash('sha256', $request->reference), 0, 12),
            uuid: 'simulation-'.substr(hash('sha256', $request->reference.'|uuid'), 0, 24),
            status: PayoutStatus::PENDING,
            provider: 'commercial-simulation',
            metadata: ['simulation' => true],
        );
    }

    public function checkStatus(string $transactionId): PayoutResultData
    {
        $this->statusCalls++;

        return new PayoutResultData(
            transaction_id: $transactionId,
            uuid: 'simulation-'.substr(hash('sha256', $transactionId.'|status'), 0, 24),
            status: PayoutStatus::COMPLETED,
            provider: 'commercial-simulation',
            metadata: ['simulation' => true],
        );
    }

    public function getRailFee(SettlementRail $rail): int
    {
        return match ($rail) {
            SettlementRail::INSTAPAY => 1_000,
            SettlementRail::PESONET => 2_500,
        };
    }
}
