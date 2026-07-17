<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\XChange\Contracts\MoneyMovementAccountingDecisionContract;
use LBHurtado\XChange\Data\Money\MoneyMovementAccountingDecisionData;

class DefaultMoneyMovementAccountingDecisionService implements MoneyMovementAccountingDecisionContract
{
    public function current(): MoneyMovementAccountingDecisionData
    {
        return new MoneyMovementAccountingDecisionData(
            candidate_models: [
                [
                    'key' => 'keep_debit_at_issuance',
                    'label' => 'Keep Debit At Issuance',
                    'effect' => 'Wallet is debited when a Pay Code is issued. Expiry and cancellation do not automatically credit funds.',
                    'risk' => 'Operator usable-balance views must keep subtracting active outstanding liability to avoid overestimating available funds.',
                    'money_movement_change' => false,
                ],
                [
                    'key' => 'debit_at_issuance_refund_on_terminal_release',
                    'label' => 'Debit At Issuance With Terminal Release',
                    'effect' => 'Wallet is debited at issuance, then credited back when a Pay Code expires or is cancelled with remaining value.',
                    'risk' => 'Requires idempotent release accounting, terminal-state guards, and double-release protection.',
                    'money_movement_change' => true,
                ],
                [
                    'key' => 'reserve_at_issuance_debit_at_redemption',
                    'label' => 'Reserve At Issuance, Debit At Redemption',
                    'effect' => 'Issuance creates a reservation. Redemption consumes the reservation. Expiry and cancellation release it.',
                    'risk' => 'Requires wallet reservation ledger semantics and migration from the current debit-at-issuance behavior.',
                    'money_movement_change' => true,
                ],
            ],
            required_decisions: [
                'Confirm whether wallet owns reservation and release ledger semantics.',
                'Confirm whether x-change owns Pay Code lifecycle triggers for release requests.',
                'Confirm idempotency keys for expiry, cancellation, redemption, and partial-claim releases.',
                'Confirm migration posture for existing debit-at-issuance Pay Codes.',
                'Confirm Cockpit labels distinguishing ledger truth from operational estimates.',
            ],
        );
    }
}
