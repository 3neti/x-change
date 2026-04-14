<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Listeners;

use LBHurtado\Voucher\Events\VoucherDisbursementSucceeded;
use LBHurtado\XChange\Contracts\DisbursementReconciliationStoreContract;
use LBHurtado\XChange\Contracts\DisbursementStatusResolverContract;

class RecordSuccessfulVoucherDisbursement
{
    public function __construct(
        protected DisbursementReconciliationStoreContract $reconciliations,
        protected DisbursementStatusResolverContract $statusResolver,
    ) {}

    public function handle(VoucherDisbursementSucceeded $event): void
    {
        $voucher = $event->voucher;
        $request = $event->request;
        $result = $event->result;

        $resolvedStatus = $this->statusResolver->resolveFromGatewayResponse($result);

        $this->reconciliations->record([
            'voucher_id' => $voucher->id,
            'voucher_code' => $voucher->code,
            'claim_type' => 'redeem',
            'provider' => $result->provider ?? 'unknown',
            'provider_reference' => $request->reference,
            'provider_transaction_id' => $result->transaction_id,
            'transaction_uuid' => $result->uuid,
            'status' => $resolvedStatus,
            'internal_status' => 'recorded',
            'amount' => $request->amount,
            'currency' => 'PHP',
            'bank_code' => $request->bank_code,
            'account_number_masked' => $this->maskAccountNumber($request->account_number),
            'settlement_rail' => $request->settlement_rail,
            'attempt_count' => 1,
            'attempted_at' => now(),
            'completed_at' => $resolvedStatus === 'succeeded' ? now() : null,
            'raw_request' => $request->toArray(),
            'raw_response' => method_exists($result, 'toArray')
                ? $result->toArray()
                : [
                    'transaction_id' => $result->transaction_id ?? null,
                    'uuid' => $result->uuid ?? null,
                    'status' => $result->status?->value ?? null,
                    'provider' => $result->provider ?? null,
                    'metadata' => $result->metadata ?? null,
                ],
            'meta' => [
                'flow' => 'redeem',
                'voucher_code' => $voucher->code,
                'slice_number' => $event->sliceNumber,
                'cash_withdrawal_uuid' => data_get($voucher->metadata, 'disbursement.cash_withdrawal_uuid'),
            ],
        ]);
    }

    protected function maskAccountNumber(?string $accountNumber): ?string
    {
        if ($accountNumber === null || $accountNumber === '') {
            return null;
        }

        $length = strlen($accountNumber);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4).substr($accountNumber, -4);
    }
}
