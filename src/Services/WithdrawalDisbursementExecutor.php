<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\EmiCore\Contracts\PendingPayoutApproval;
use LBHurtado\EmiCore\Data\PayoutPartyData;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Data\PayoutSourceAccountData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\DisbursementReconciliationStoreContract;
use LBHurtado\XChange\Contracts\DisbursementStatusResolverContract;
use LBHurtado\XChange\Data\WithdrawalDisbursementExecutionData;
use LBHurtado\XChange\Models\BankAccountAssignment;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Services\Banking\AuthorizedPayoutBankSource;
use LBHurtado\XChange\Services\Banking\PayoutBankSourceSnapshot;
use LBHurtado\XChange\Services\Banking\VoucherBankSourceSnapshot;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
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
        bool $approvedContinuation = false,
    ): WithdrawalDisbursementExecutionData {
        if (config('x-change.payout_locality.submission_guard_enabled', false)) {
            app(ClaimPayoutSubmission::class)->claim($voucher, $input->reference, $approvedContinuation, $input->toAuditArray());
        }
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
            'raw_request' => $input->toAuditArray(),
            'meta' => [
                'flow' => 'withdraw',
                'voucher_code' => $voucher->code,
                'slice_number' => $sliceNumber,
            ],
        ];

        // Record settlement intent BEFORE calling the provider
        $recorded = $this->reconciliations->record($intentAttributes);
        $existingSource = isset($recorded->id)
            ? DisbursementReconciliation::query()->find($recorded->id)?->bank_source_snapshot
            : null;
        $voucherSource = app(VoucherBankSourceSnapshot::class)->forVoucher($voucher);
        $reservedConnection = data_get($voucher->metadata, 'treasury.pay_code_reservation.connection_reference');
        if ($voucherSource === null && is_string($reservedConnection) && BankAccountAssignment::query()
            ->where('treasury_connection_reference', $reservedConnection)->exists()) {
            throw new RuntimeException('The Pay Code has no frozen source for its assigned bank account.');
        }
        if (is_array($voucherSource) && is_string($reservedConnection)
            && ($voucherSource['treasury_connection_reference'] ?? null) !== $reservedConnection) {
            throw new RuntimeException('The Pay Code Treasury reservation does not match its frozen source.');
        }
        if (is_array($voucherSource) && is_string($reservedConnection)) {
            $connections = app(TreasuryProviderConnectionCatalog::class)->active([$reservedConnection]);
            $connection = count($connections) === 1 ? $connections[0] : null;
            if ($connection === null || $connection->provider !== ($voucherSource['provider'] ?? null)
                || $connection->inventoryReference !== ($voucherSource['treasury_inventory_reference'] ?? null)
                || $connection->settlementResourceReference !== ($voucherSource['treasury_resource_reference'] ?? null)) {
                throw new RuntimeException('The Pay Code Treasury connection no longer matches its frozen source.');
            }
        }
        if (is_array($voucherSource)) {
            if (is_array($existingSource) && $existingSource !== $voucherSource) {
                throw new RuntimeException('The payout source does not match the Pay Code source.');
            }
            if ($input->source_account !== null && (
                $input->source_account->provider !== $voucherSource['provider']
                || $input->source_account->account_number !== $voucherSource['account_number']
                || $input->source_account->customer_id !== $voucherSource['customer_id']
            )) {
                throw new RuntimeException('The payout source does not match the Pay Code source.');
            }
        }
        if ($input->source_account !== null || is_array($existingSource) || is_array($voucherSource)) {
            if (! isset($recorded->id)) {
                throw new RuntimeException('Payout source snapshot requires a durable reconciliation.');
            }
            $source = $input->source_account;
            $snapshot = app(PayoutBankSourceSnapshot::class)->remember(
                (int) $recorded->id,
                fn (): array => $voucherSource ?? app(AuthorizedPayoutBankSource::class)->resolve($voucher->owner, $source),
            );
            $input->source_account = PayoutSourceAccountData::from($snapshot);
        }
        $existingSnapshot = isset($recorded->id)
            ? DisbursementReconciliation::query()->find($recorded->id)?->payout_identity_snapshot
            : null;
        if ($input->sender !== null || $input->recipient !== null || is_array($existingSnapshot)) {
            if (! isset($recorded->id)) {
                throw new RuntimeException('Payout identity snapshot requires a durable reconciliation.');
            }
            $snapshot = app(PayoutLocalitySnapshot::class)->remember((int) $recorded->id, fn (): array => [
                'sender' => $input->sender?->toArray(),
                'recipient' => $input->recipient?->toArray(),
            ]);
            $input->sender = is_array($snapshot['sender'] ?? null) ? PayoutPartyData::from($snapshot['sender']) : null;
            $input->recipient = is_array($snapshot['recipient'] ?? null) ? PayoutPartyData::from($snapshot['recipient']) : null;
        }

        try {
            $response = $this->gateway->disburse($input);
            $status = $this->statusResolver->resolveFromGatewayResponse($response);
            $rawResponse = app(PayoutResultAudit::class)->summarize($response);
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
        } catch (PendingPayoutApproval $e) {
            $this->reconciliations->record(array_merge($intentAttributes, [
                'provider' => $e->provider(),
                'status' => 'approval_required',
                'internal_status' => 'recorded',
                'raw_response' => ['approval_required' => true],
                'needs_review' => false,
            ]));

            throw $e;
        } catch (Throwable $e) {
            $status = $this->statusResolver->resolveFromGatewayException($e);
            $containsSensitiveAccountData = $input->sender !== null || $input->recipient !== null || $input->source_account !== null;
            $errorMessage = $containsSensitiveAccountData ? 'Provider submission failed; reconciliation may be required.' : $e->getMessage();

            // Update intent with failure
            $this->reconciliations->record(array_merge($intentAttributes, [
                'status' => $status,
                'internal_status' => 'recorded',
                'raw_response' => [
                    'exception' => $e::class,
                    'message' => $errorMessage,
                ],
                'needs_review' => $status === 'unknown',
                'review_reason' => $status === 'unknown'
                    ? 'Gateway outcome uncertain'
                    : null,
                'error_message' => $errorMessage,
            ]));

            throw new RuntimeException('Disbursement failed: '.$errorMessage, previous: $containsSensitiveAccountData ? null : $e);
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
