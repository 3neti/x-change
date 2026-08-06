<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\XChange\Contracts\MoneyMovementLifecycleTriggerMatrixContract;
use LBHurtado\XChange\Data\Money\MoneyMovementLifecycleTriggerMatrixData;

class DefaultMoneyMovementLifecycleTriggerMatrixService implements MoneyMovementLifecycleTriggerMatrixContract
{
    public function current(): MoneyMovementLifecycleTriggerMatrixData
    {
        return new MoneyMovementLifecycleTriggerMatrixData(
            status: 'partially_operational',
            triggers: [
                [
                    'event' => 'pay_code_issued',
                    'current_effect' => 'wallet_debited',
                    'future_effect' => 'reserve_funds',
                    'owner' => 'x-change requests; wallet records',
                    'idempotency_key' => 'pay-code-issuance-idempotency-key',
                    'enabled' => false,
                ],
                [
                    'event' => 'pay_code_redeemed',
                    'current_effect' => 'voucher_claim_recorded_and_disbursement_attempted',
                    'future_effect' => 'capture_reserved_funds',
                    'owner' => 'x-change requests; wallet records',
                    'idempotency_key' => 'voucher-claim-execution-idempotency-key',
                    'enabled' => false,
                ],
                [
                    'event' => 'pay_code_partially_claimed',
                    'current_effect' => 'remaining_claim_balance_tracked',
                    'future_effect' => 'capture_partial_reserved_funds_and_keep_remainder_reserved',
                    'owner' => 'x-change requests; wallet records',
                    'idempotency_key' => 'voucher-claim-number-idempotency-key',
                    'enabled' => false,
                ],
                [
                    'event' => 'pay_code_expired',
                    'current_effect' => 'release_unclaimed_client_funds_reserve_and_exclude_liability',
                    'future_effect' => 'release_remaining_reserved_funds_for_additional_reservation_origins',
                    'owner' => 'x-change terminal lifecycle requests; wallet records',
                    'idempotency_key' => 'voucher-expiry-release-idempotency-key',
                    'enabled' => true,
                ],
                [
                    'event' => 'pay_code_cancelled',
                    'current_effect' => 'release_unclaimed_treasury_reserve_and_exclude_liability',
                    'future_effect' => 'release_remaining_reserved_funds_for_additional_reservation_origins',
                    'owner' => 'x-change terminal lifecycle requests; wallet records',
                    'idempotency_key' => 'voucher-cancellation-release-idempotency-key',
                    'enabled' => true,
                ],
                [
                    'event' => 'provider_disbursement_failed_after_capture',
                    'current_effect' => 'reconciliation_required',
                    'future_effect' => 'reverse_or_reconcile_captured_funds_by_policy',
                    'owner' => 'x-change reconciliation requests; wallet records',
                    'idempotency_key' => 'provider-failure-reversal-idempotency-key',
                    'enabled' => false,
                ],
            ],
            open_questions: [
                'Which additional reservation origins require a terminal return operation?',
                'How should failed provider disbursement after capture be reversed versus reconciled?',
                'Which package owns the durable reservation ledger table and API?',
            ],
            redactions: [
                'payloads' => 'trigger-matrix-only',
                'wallet_payloads_exposed' => false,
                'voucher_payloads_exposed' => false,
                'mutates_wallets' => true,
                'reserves_funds' => false,
                'captures_funds' => false,
                'releases_funds' => true,
                'reverses_funds' => false,
            ],
        );
    }
}
