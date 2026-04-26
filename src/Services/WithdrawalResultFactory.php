<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Cash\Exceptions\WithdrawalApprovalRequired;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Data\PayoutResultData;
use LBHurtado\EmiCore\Enums\SettlementRail;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Redemption\WithdrawPayCodeResultData;
use LBHurtado\XChange\Data\WithdrawalPipelineContextData;

class WithdrawalResultFactory
{
    public function make(
        Voucher $voucher,
        Contact $contact,
        PayoutRequestData $input,
        PayoutResultData $response,
        float $withdrawAmount,
        int $sliceNumber,
    ): WithdrawPayCodeResultData {
        $remainingBalance = method_exists($voucher, 'getRemainingBalance')
            ? (float) $voucher->getRemainingBalance()
            : null;

        $remainingSlices = method_exists($voucher, 'getRemainingSlices')
            ? (int) $voucher->getRemainingSlices()
            : null;

        $rail = SettlementRail::from($input->settlement_rail);
        $providerName = $response->provider ?? 'unknown';

        return new WithdrawPayCodeResultData(
            voucher_code: (string) $voucher->code,
            withdrawn: true,
            status: 'withdrawn',
            requested_amount: $withdrawAmount,
            disbursed_amount: $withdrawAmount,
            currency: 'PHP',
            remaining_balance: $remainingBalance,
            slice_number: $sliceNumber,
            remaining_slices: $remainingSlices,
            slice_mode: method_exists($voucher, 'getSliceMode') ? $voucher->getSliceMode() : null,
            redeemer: [
                'mobile' => $contact->mobile,
                'country' => $contact->country ?? null,
                'contact_id' => $contact->id,
            ],
            bank_account: [
                'bank_code' => $input->bank_code,
                'account_number' => $input->account_number,
            ],
            disbursement: [
                'status' => $response->status->value,
                'bank_code' => $input->bank_code,
                'account_number' => $input->account_number,
                'transaction_id' => $response->transaction_id,
                'gateway' => $providerName,
                'settlement_rail' => $rail->value,
            ],
            messages: ['Voucher withdrawal successful.'],
        );
    }

    public function approvalRequired(
        WithdrawalPipelineContextData $context,
        WithdrawalApprovalRequired $exception,
    ): WithdrawPayCodeResultData {
        return new WithdrawPayCodeResultData(
            voucher_code: (string) $context->voucher->code,
            withdrawn: false,
            status: 'approval_required',
            requested_amount: (float) ($context->withdrawAmount ?? data_get($context->payload, 'amount', 0)),
            disbursed_amount: 0,
            currency: 'PHP',
            remaining_balance: method_exists($context->voucher, 'getRemainingBalance')
                ? (float) $context->voucher->getRemainingBalance()
                : null,
            slice_number: $context->sliceNumber,
            remaining_slices: method_exists($context->voucher, 'getRemainingSlices')
                ? $context->voucher->getRemainingSlices()
                : null,
            slice_mode: method_exists($context->voucher, 'getSliceMode')
                ? $context->voucher->getSliceMode()
                : null,
            redeemer: $context->contact ? [
                'mobile' => $context->contact->mobile,
                'country' => $context->contact->country ?? null,
                'contact_id' => $context->contact->id,
            ] : [],
            bank_account: $context->bankAccount ? [
                'bank_code' => $context->bankAccount->getBankCode(),
                'account_number' => $context->bankAccount->getAccountNumber(),
            ] : [],
            disbursement: [],
            messages: [$exception->getMessage()],
            approval_requirements: method_exists($exception, 'requirements')
                ? $exception->requirements()
                : ['approval'],
            approval_meta: method_exists($exception, 'meta')
                ? $exception->meta()
                : [
                    'source' => 'cash_authorization',
                ],
        );
    }
}
