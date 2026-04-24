<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Enums\PayoutStatus;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\DisbursementReconciliationStoreContract;
use LBHurtado\XChange\Contracts\DisbursementStatusResolverContract;
use LBHurtado\XChange\Data\WithdrawalDisbursementExecutionData;
use RuntimeException;
use Throwable;

class WithdrawalDisbursementExecutor
{
    public function __construct(
        protected PayoutProvider $gateway,
        protected DisbursementReconciliationStoreContract $reconciliations,
        protected DisbursementStatusResolverContract $statusResolver,
    ) {}

    public function execute(
        Voucher $voucher,
        PayoutRequestData $input,
        int $sliceNumber,
    ): WithdrawalDisbursementExecutionData {
        try {
            $response = $this->gateway->disburse($input);

            if ($response->status === PayoutStatus::FAILED) {
                throw new RuntimeException('Gateway returned failed status - disbursement failed');
            }

            $status = $this->statusResolver->resolveFromGatewayResponse($response);

            $this->reconciliations->record([
                'voucher_id' => $voucher->id,
                'voucher_code' => $voucher->code,
                'claim_type' => 'withdraw',
                'provider' => $response->provider ?? 'unknown',
                'provider_reference' => $input->reference,
                'provider_transaction_id' => $response->transaction_id ?? null,
                'transaction_uuid' => $response->uuid ?? null,
                'status' => $status,
                'internal_status' => 'recorded',
                'amount' => $input->amount,
                'currency' => 'PHP',
                'bank_code' => $input->bank_code,
                'account_number_masked' => $this->maskAccountNumber($input->account_number),
                'settlement_rail' => $input->settlement_rail,
                'attempt_count' => 1,
                'attempted_at' => now(),
                'completed_at' => $status === 'succeeded' ? now() : null,
                'raw_request' => $input->toArray(),
                'raw_response' => method_exists($response, 'toArray') ? $response->toArray() : [
                    'status' => $response->status?->value ?? null,
                    'transaction_id' => $response->transaction_id ?? null,
                    'uuid' => $response->uuid ?? null,
                    'provider' => $response->provider ?? null,
                ],
                'meta' => [
                    'flow' => 'withdraw',
                    'voucher_code' => $voucher->code,
                    'slice_number' => $sliceNumber,
                ],
            ]);

            return new WithdrawalDisbursementExecutionData(
                input: $input,
                response: $response,
                status: $status,
                message: null,
            );
        } catch (Throwable $e) {
            $status = $this->statusResolver->resolveFromGatewayException($e);

            $this->reconciliations->record([
                'voucher_id' => $voucher->id,
                'voucher_code' => $voucher->code,
                'claim_type' => 'withdraw',
                'provider' => 'unknown',
                'provider_reference' => $input->reference,
                'provider_transaction_id' => null,
                'transaction_uuid' => null,
                'status' => $status,
                'internal_status' => 'recorded',
                'amount' => $input->amount,
                'currency' => 'PHP',
                'bank_code' => $input->bank_code,
                'account_number_masked' => $this->maskAccountNumber($input->account_number),
                'settlement_rail' => $input->settlement_rail,
                'attempt_count' => 1,
                'attempted_at' => now(),
                'raw_request' => $input->toArray(),
                'raw_response' => [
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ],
                'needs_review' => $status === 'unknown',
                'review_reason' => $status === 'unknown'
                    ? 'Gateway outcome uncertain'
                    : null,
                'error_message' => $e->getMessage(),
                'meta' => [
                    'flow' => 'withdraw',
                    'voucher_code' => $voucher->code,
                    'slice_number' => $sliceNumber,
                ],
            ]);

            throw new RuntimeException('Disbursement failed: '.$e->getMessage(), previous: $e);
        }
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
