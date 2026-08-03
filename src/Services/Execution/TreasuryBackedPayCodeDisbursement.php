<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Execution;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Events\DisbursementConfirmed;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Services\WithdrawalBankAccountResolver;
use LBHurtado\XChange\Services\WithdrawalDisbursementExecutor;
use LBHurtado\XChange\Services\WithdrawalExecutionContextResolver;
use LBHurtado\XChange\Services\WithdrawalPayoutRequestFactory;
use RuntimeException;
use Throwable;

final readonly class TreasuryBackedPayCodeDisbursement
{
    public function __construct(
        private WithdrawalBankAccountResolver $bankAccounts,
        private WithdrawalExecutionContextResolver $executionContexts,
        private WithdrawalPayoutRequestFactory $payoutRequests,
        private WithdrawalDisbursementExecutor $disbursements,
    ) {}

    public function handle(Voucher $voucher): Voucher
    {
        $lock = Cache::lock(
            'x-change:treasury-pay-code-disbursement:'.$voucher->getKey(),
            120,
        );

        if (! $lock->get()) {
            throw new RuntimeException('Another Treasury Pay Code disbursement is already running.');
        }

        try {
            $voucher = $voucher->fresh() ?? $voucher;
            $reservation = (array) data_get(
                $voucher->metadata,
                'treasury.pay_code_reservation',
                [],
            );

            if (data_get($reservation, 'status') !== 'reserved') {
                return $voucher;
            }

            if ($this->existingAttempt($voucher) instanceof DisbursementReconciliation) {
                return $voucher;
            }

            $contact = $voucher->contact;

            if (! $contact instanceof Contact) {
                throw new RuntimeException('Treasury Pay Code disbursement requires a verified recipient contact.');
            }

            $bankAccount = $this->bankAccounts->resolve($voucher, $contact, []);
            $amountMinor = (int) data_get($reservation, 'amount_minor', 0);

            if ($amountMinor <= 0) {
                throw new RuntimeException('Treasury Pay Code disbursement requires a positive reserved amount.');
            }

            $executionContext = $this->executionContexts->resolve(
                $voucher,
                $bankAccount->getAccountNumber(),
            );
            $request = $this->payoutRequests->make(
                voucher: $voucher,
                contact: $contact,
                bankAccount: $bankAccount,
                providerReference: $executionContext->providerReference,
                amount: $amountMinor / 100,
            );

            try {
                $execution = $this->disbursements->execute(
                    voucher: $voucher,
                    input: $request,
                    sliceNumber: $executionContext->sliceNumber,
                );
            } catch (Throwable $exception) {
                $this->markForReconciliation($voucher, $request->toArray(), $exception);

                return $voucher->refresh();
            }

            $reconciliation = DisbursementReconciliation::query()
                ->where('voucher_id', $voucher->getKey())
                ->where('provider_reference', $request->reference)
                ->latest('id')
                ->firstOrFail();
            $reconciliationMetadata = (array) $reconciliation->meta;
            unset($reconciliationMetadata['slice_number']);
            $reconciliationMetadata['flow'] = 'treasury_pay_code';
            $reconciliation->forceFill([
                'meta' => $reconciliationMetadata,
            ])->save();

            $this->recordProviderResult(
                $voucher,
                $execution->status,
                $execution->response->provider,
                $execution->response->transaction_id,
                $execution->response->uuid,
                $request->toArray(),
            );

            if ($execution->status === 'succeeded') {
                DisbursementConfirmed::dispatch($reconciliation);
            }

            return $voucher->refresh();
        } finally {
            $lock->release();
        }
    }

    private function existingAttempt(Voucher $voucher): ?DisbursementReconciliation
    {
        return DisbursementReconciliation::query()
            ->where('voucher_id', $voucher->getKey())
            ->latest('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $request
     */
    private function markForReconciliation(
        Voucher $voucher,
        array $request,
        Throwable $exception,
    ): void {
        $metadata = (array) $voucher->metadata;
        data_set($metadata, 'disbursement', [
            'gateway' => 'unknown',
            'status' => 'pending',
            'amount' => (float) data_get($request, 'amount', 0),
            'currency' => 'PHP',
            'settlement_rail' => data_get($request, 'settlement_rail'),
            'recipient_identifier' => data_get($request, 'account_number'),
            'requires_reconciliation' => true,
            'error' => $exception->getMessage(),
            'metadata' => [
                'bank_code' => data_get($request, 'bank_code'),
            ],
        ]);
        $voucher->forceFill(['metadata' => $metadata])->saveQuietly();

        Log::warning('[XChange] Treasury Pay Code provider submission requires reconciliation.', [
            'voucher_code' => $voucher->code,
            'exception' => $exception::class,
        ]);
    }

    /**
     * @param  array<string, mixed>  $request
     */
    private function recordProviderResult(
        Voucher $voucher,
        string $status,
        ?string $provider,
        ?string $transactionId,
        ?string $transactionUuid,
        array $request,
    ): void {
        $metadata = (array) $voucher->metadata;
        data_set($metadata, 'disbursement', [
            'gateway' => $provider ?? 'unknown',
            'transaction_id' => $transactionId,
            'status' => $status,
            'amount' => (float) data_get($request, 'amount', 0),
            'currency' => 'PHP',
            'settlement_rail' => data_get($request, 'settlement_rail'),
            'fee_amount' => 0,
            'recipient_identifier' => data_get($request, 'account_number'),
            'disbursed_at' => now()->toIso8601String(),
            'transaction_uuid' => $transactionUuid,
            'payment_method' => 'bank_transfer',
            'requires_reconciliation' => $status !== 'succeeded',
            'metadata' => [
                'bank_code' => data_get($request, 'bank_code'),
                'rail' => data_get($request, 'settlement_rail'),
            ],
        ]);
        $voucher->forceFill(['metadata' => $metadata])->saveQuietly();
    }
}
