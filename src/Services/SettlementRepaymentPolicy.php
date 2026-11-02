<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Validation\ValidationException;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\DisbursementReconciliation;

final class SettlementRepaymentPolicy
{
    /** @param array<string, mixed> $instructions */
    public static function applies(array $instructions): bool
    {
        return ($instructions['voucher_type'] ?? null) === 'settlement'
            && data_get($instructions, 'rules.settlement_mode') === 'disburse_then_repay';
    }

    public static function forVoucher(Voucher $voucher): bool
    {
        return self::applies((array) data_get($voucher->metadata, 'instructions', []));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function normalize(array $input): array
    {
        $mode = data_get($input, 'rules.settlement_mode');
        if (data_get($input, 'metadata.custom.cockpit.template_key') === 'loan'
            && (data_get($input, 'metadata.custom.cockpit.template_version') !== 1
                || data_get($input, 'execution.schema') !== 'voucher.execution.v2'
                || $mode !== 'disburse_then_repay')) {
            throw ValidationException::withMessages([
                'rules.settlement_mode' => 'The loan template requires the disburse, then collect workflow.',
            ]);
        }
        if ($mode === null) {
            if (data_get($input, 'voucher_type') === 'settlement'
                && data_get($input, 'execution.schema') === 'voucher.execution.v2'
                && blank(data_get($input, 'execution.settlement.driver'))
                && data_get($input, 'execution.metadata.settlement_workflow') !== 'collect_for_settlement') {
                throw ValidationException::withMessages([
                    'rules.settlement_mode' => 'Select a settlement workflow.',
                ]);
            }

            return $input;
        }

        $principal = (int) round((float) data_get($input, 'cash.amount', 0) * 100);
        $target = (int) round((float) ($input['target_amount'] ?? 0) * 100);
        $payment = (int) round((float) data_get($input, 'rules.min_payment', 0) * 100);
        $maximum = (int) round((float) data_get($input, 'rules.max_payment', 0) * 100);

        if (! self::applies($input) || $principal <= 0 || $target < $principal
            || $payment <= 0 || $payment !== $maximum || $target % $payment !== 0
            || data_get($input, 'execution.driver', 'default') !== 'default'
            || data_get($input, 'stored_value.enabled', false)
            || (int) (data_get($input, 'cash.slices') ?? 1) !== 1
            || data_get($input, 'cash.slice_mode', 'fixed') === 'open'
            || data_get($input, 'claim.default_outcome', 'provider_disbursement') !== 'provider_disbursement') {
            throw ValidationException::withMessages([
                'rules.settlement_mode' => 'Disburse and repay requires a funded principal, a repayment target, and equal fixed payments that divide the target.',
            ]);
        }

        data_set($input, 'metadata.flow_type', 'settlement');
        data_set($input, 'rules.allow_overpayment', false);
        data_set($input, 'rules.auto_close_on_full_payment', true);

        return $input;
    }

    public static function disbursed(Voucher $voucher): bool
    {
        $payout = DisbursementReconciliation::query()
            ->where('voucher_id', $voucher->getKey())->where('claim_type', '!=', 'value_flow')
            ->latest('id')->first();

        if ($payout === null || $payout->needs_review) {
            return false;
        }

        if ($payout->status === 'succeeded' && $payout->internal_status === 'finalized') {
            return true;
        }

        return $payout->status === 'pending'
            && $payout->internal_status === 'recorded'
            && data_get($payout->meta, 'provider_response.received') === true
            && data_get($payout->meta, 'provider_response.status') === 'pending'
            && filled($payout->provider_transaction_id);
    }

    public static function paymentMinor(Voucher $voucher): int
    {
        return (int) round((float) data_get($voucher->instructions->rules, 'min_payment', 0) * 100);
    }
}
