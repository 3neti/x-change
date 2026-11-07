<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Execution;

use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\EmiCore\Data\PayoutPartyData;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Data\PayoutResultData;
use LBHurtado\EmiCore\Enums\SettlementRail;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Services\ClaimPayoutLocalityScope;
use LBHurtado\XChange\Services\ClaimPayoutParties;
use LBHurtado\XChange\Services\WithdrawalDisbursementExecutor;

final readonly class GuardedLegacyPayoutProvider implements PayoutProvider
{
    public function __construct(
        private Voucher $voucher,
        private PayoutProvider $provider,
        private WithdrawalDisbursementExecutor $executor,
        private ClaimPayoutParties $parties,
        private ClaimPayoutLocalityScope $scope,
    ) {}

    public function disburse(PayoutRequestData $request): PayoutResultData
    {
        $resolved = $this->parties->resolve($this->voucher, [
            '_meta' => ['idempotency_key' => $this->scope->get()],
        ], (string) ($this->voucher->contact?->name ?? ''));
        $request->sender = isset($resolved['sender']) ? PayoutPartyData::from($resolved['sender']) : null;
        $request->recipient = isset($resolved['recipient']) ? PayoutPartyData::from($resolved['recipient']) : null;
        $sliceNumber = preg_match('/-S([1-9][0-9]*)$/', $request->reference, $matches) === 1 ? (int) $matches[1] : 1;

        return $this->executor->execute($this->voucher, $request, $sliceNumber)->response;
    }

    public function checkStatus(string $transactionId): PayoutResultData
    {
        return $this->provider->checkStatus($transactionId);
    }

    public function getRailFee(SettlementRail $rail): int
    {
        return $this->provider->getRailFee($rail);
    }
}
