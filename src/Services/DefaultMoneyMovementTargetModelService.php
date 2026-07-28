<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\XChange\Contracts\MoneyMovementTargetModelContract;
use LBHurtado\XChange\Data\Money\MoneyMovementTargetModelData;

class DefaultMoneyMovementTargetModelService implements MoneyMovementTargetModelContract
{
    public function current(): MoneyMovementTargetModelData
    {
        return new MoneyMovementTargetModelData(
            approval_requirements: [
                'Approve wallet-owned reservation ledger semantics.',
                'Approve x-change-owned Pay Code lifecycle trigger mapping.',
                'Approve idempotency keys for reservation, capture, release, and reversal events.',
                'Approve migration posture for existing debit-at-issuance Pay Codes.',
                'Approve operator-facing labels for ledger truth versus operational estimates.',
            ],
            implementation_boundaries: [
                'Target model selection does not change current issuance debit behavior.',
                'Reservation creation must be owned by wallet or an explicitly approved wallet adapter.',
                'Unclaimed cancellation releases eligible Client Funds reservations exactly once; expiry and additional reservation origins remain separately guarded.',
                'Redemption may capture reserved funds only after partial-claim and overdraw semantics are protected by tests.',
                'Cockpit remains read-only until mutation endpoints and authorization gates are separately approved.',
            ],
        );
    }
}
