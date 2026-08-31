<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\EmiCore\Data\PayoutRequestData;
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
        $intentAttributes = [
            'voucher_id' => $voucher->id,
            'voucher_code' => $voucher->code,
            'claim_type' => 'withdraw',
            'provider' => 'unknown',
            'provider_reference' => $input->reference,
            'amount' => $input->amount,
            'currency' => 'PHP',
            'bank_code' => $input->bank_code,
            'account_number_masked' => $this->maskAccountNumber($input->account_number),
            'settlement_rail' => $input->settlement_rail,
            'status' => 'intent',
            'internal_status' => 'intent',
            'attempt_count' => 1,
            'attempted_at' => now(),
            'raw_request' => $input->toArray(),
            'meta' => [
                'flow' => 'withdraw',
                'voucher_code' => $voucher->code,
                'slice_number' => $sliceNumber,
            ],
        ];

        // Record settlement intent BEFORE calling the provider
        $this->reconciliations->record($intentAttributes);

        try {
            $response = $this->gateway->disburse($input);
            $status = $this->statusResolver->resolveFromGatewayResponse($response);
            $rawResponse = method_exists($response, 'toArray') ? $response->toArray() : [
                'status' => $response->status?->value ?? null,
                'transaction_id' => $response->transaction_id ?? null,
                'uuid' => $response->uuid ?? null,
                'provider' => $response->provider ?? null,
            ];
            $responseMetadata = array_replace($intentAttributes['meta'], [
                'provider_response' => [
                    'received' => true,
                    'status' => $status,
                    'transaction_identifier_present' => filled($response->transaction_id),
                ],
            ]);

            // Update intent with provider response
            $this->reconciliations->record(array_merge($intentAttributes, [
                'provider' => $response->provider ?? 'unknown',
                'provider_transaction_id' => $response->transaction_id ?? null,
                'transaction_uuid' => $response->uuid ?? null,
                'status' => $status,
                'internal_status' => 'recorded',
                'completed_at' => in_array($status, ['succeeded', 'failed'], true) ? now() : null,
                'raw_response' => $rawResponse,
                'needs_review' => $status === 'unknown',
                'review_reason' => $status === 'unknown'
                    ? 'Gateway returned an unknown status'
                    : null,
                'error_message' => $status === 'failed'
                    ? 'Gateway returned failed status - disbursement failed'
                    : null,
                'meta' => $responseMetadata,
            ]));

            return new WithdrawalDisbursementExecutionData(
                input: $input,
                response: $response,
                status: $status,
                message: $status === 'failed'
                    ? 'Gateway returned failed status - disbursement failed'
                    : null,
            );
        } catch (Throwable $e) {
            $status = $this->statusResolver->resolveFromGatewayException($e);

            // Update intent with failure
            $this->reconciliations->record(array_merge($intentAttributes, [
                'status' => $status,
                'internal_status' => 'recorded',
                'raw_response' => [
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ],
                'needs_review' => $status === 'unknown',
                'review_reason' => $status === 'unknown'
                    ? 'Gateway outcome uncertain'
                    : null,
                'error_message' => $e->getMessage(),
            ]));

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
