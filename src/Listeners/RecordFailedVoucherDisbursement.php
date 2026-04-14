<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Listeners;

use LBHurtado\Voucher\Events\VoucherDisbursementFailed;
use LBHurtado\XChange\Contracts\DisbursementReconciliationStoreContract;
use LBHurtado\XChange\Contracts\DisbursementStatusResolverContract;

class RecordFailedVoucherDisbursement
{
    public function __construct(
        protected DisbursementReconciliationStoreContract $reconciliations,
        protected DisbursementStatusResolverContract $statusResolver,
    ) {}

    public function handle(VoucherDisbursementFailed $event): void
    {
        $voucher = $event->voucher;
        $request = $event->request;
        $exception = $event->exception;

        $resolvedStatus = $this->statusResolver->resolveFromGatewayException($exception);

        $this->reconciliations->record([
            'voucher_id' => $voucher->id,
            'voucher_code' => $voucher->code,
            'claim_type' => 'redeem',
            'provider' => 'unknown',
            'provider_reference' => $request->reference,
            'provider_transaction_id' => null,
            'transaction_uuid' => null,
            'status' => $resolvedStatus,
            'internal_status' => 'recorded',
            'amount' => $request->amount,
            'currency' => 'PHP',
            'bank_code' => $request->bank_code,
            'account_number_masked' => $this->maskAccountNumber($request->account_number),
            'settlement_rail' => $request->settlement_rail,
            'attempt_count' => 1,
            'attempted_at' => now(),
            'completed_at' => null,
            'raw_request' => $request->toArray(),
            'raw_response' => [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ],
            'needs_review' => $resolvedStatus === 'unknown',
            'review_reason' => $resolvedStatus === 'unknown'
                ? 'Gateway outcome uncertain'
                : null,
            'error_message' => $exception->getMessage(),
            'meta' => [
                'flow' => 'redeem',
                'voucher_code' => $voucher->code,
                'slice_number' => $event->sliceNumber,
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
