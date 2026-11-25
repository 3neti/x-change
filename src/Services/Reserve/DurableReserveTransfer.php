<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Reserve;

use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Contracts\AccountTransferProvider;
use LBHurtado\EmiCore\Contracts\IdempotentAccountTransferProvider;
use LBHurtado\EmiCore\Data\Providers\AccountTransferEvidenceData;
use LBHurtado\EmiCore\Data\Providers\AccountTransferRequestData;
use LBHurtado\EmiCore\Data\Providers\AccountTransferResultData;
use LBHurtado\EmiCore\Enums\AccountTransferStatus;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionOperationType;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryPositionOperation;
use LBHurtado\XChange\Models\ReserveTransfer;
use RuntimeException;
use Throwable;

/** Persists bank submission separately from local treasury posting. */
final class DurableReserveTransfer
{
    public function prepare(AccountTransferRequestData $request, string $sourceConnection, string $reserveConnection): ReserveTransfer
    {
        if (trim($sourceConnection) === '' || trim($reserveConnection) === '' || $sourceConnection === $reserveConnection) {
            throw new RuntimeException('Distinct treasury connections are required.');
        }
        $payload = $request->toArray();
        $fingerprint = hash('sha256', json_encode([$payload, $sourceConnection, $reserveConnection], JSON_THROW_ON_ERROR));
        $idempotencyHash = hash('sha256', $request->idempotencyKey);

        return DB::transaction(function () use ($request, $sourceConnection, $reserveConnection, $payload, $fingerprint, $idempotencyHash): ReserveTransfer {
            $transfer = ReserveTransfer::query()->where('reference', $request->reference)->lockForUpdate()->first();
            if ($transfer !== null) {
                if (! hash_equals($transfer->request_fingerprint, $fingerprint)) {
                    throw new RuntimeException('The transfer reference is already bound to different instructions.');
                }

                return $transfer;
            }

            return ReserveTransfer::query()->create([
                'reference' => $request->reference, 'provider' => $request->provider,
                'idempotency_hash' => $idempotencyHash, 'request_fingerprint' => $fingerprint,
                'request_ciphertext' => $payload, 'source_treasury_connection' => $sourceConnection,
                'reserve_treasury_connection' => $reserveConnection, 'amount_minor' => $request->amountMinor,
                'currency' => $request->currency, 'status' => 'prepared',
            ]);
        }, attempts: 3);
    }

    public function submit(ReserveTransfer $transfer, AccountTransferProvider $provider): ReserveTransfer
    {
        return $this->submitFromStatus($transfer, $provider, 'held');
    }

    public function retryUncertain(ReserveTransfer $transfer, AccountTransferProvider $provider): ReserveTransfer
    {
        if (! $provider instanceof IdempotentAccountTransferProvider) {
            throw new RuntimeException('The provider does not guarantee safe transfer replay.');
        }

        return $this->submitFromStatus($transfer, $provider, 'uncertain');
    }

    private function submitFromStatus(ReserveTransfer $transfer, AccountTransferProvider $provider, string $expectedStatus): ReserveTransfer
    {
        if (DB::transactionLevel() !== 0) {
            throw new RuntimeException('A bank transfer submission must own its database transaction.');
        }
        $claimed = DB::transaction(function () use ($transfer, $provider, $expectedStatus): ?ReserveTransfer {
            $locked = ReserveTransfer::query()->lockForUpdate()->findOrFail($transfer->getKey());
            if ($locked->provider !== $provider->providerCode()) {
                throw new RuntimeException('The transfer provider does not match.');
            }
            if ($locked->status !== $expectedStatus || ! is_string($locked->hold_operation_reference)) {
                return null;
            }
            $this->assertFrozen($locked);
            $this->assertHold($locked);
            app(ReserveIssuanceSnapshot::class)->assertFeeHold($locked);
            app(ReserveFundingOrderLink::class)->assertActive($locked);
            $locked->forceFill(['status' => 'submitting', 'submitted_at' => now()])->save();

            return $locked;
        }, attempts: 3);
        if ($claimed === null) {
            return $transfer->refresh();
        }
        try {
            $result = $provider->submit(AccountTransferRequestData::from($claimed->request_ciphertext));
        } catch (Throwable) {
            $result = new AccountTransferResultData(AccountTransferStatus::Uncertain);
        }

        return DB::transaction(function () use ($claimed, $result): ReserveTransfer {
            $locked = ReserveTransfer::query()->lockForUpdate()->findOrFail($claimed->getKey());
            if (! in_array($locked->status, ['submitting', 'uncertain'], true)
                || $locked->submitted_at === null || $claimed->submitted_at === null
                || ! $locked->submitted_at->equalTo($claimed->submitted_at)) {
                return $locked;
            }
            if ($locked->status === 'uncertain' && $result->providerTransactionId === null) {
                return $locked;
            }
            $locked->forceFill([
                'status' => match ($result->status) {
                    AccountTransferStatus::Rejected, AccountTransferStatus::Pending, AccountTransferStatus::Settled => $result->providerTransactionId === null ? 'uncertain' : 'pending',
                    AccountTransferStatus::Uncertain => 'uncertain',
                },
                'provider_transaction_id' => $result->providerTransactionId,
            ])->save();

            return $locked;
        }, attempts: 3);
    }

    /** Marks an interrupted submission uncertain without repeating the bank request. */
    public function markInterruptedUncertain(ReserveTransfer $transfer): ReserveTransfer
    {
        return DB::transaction(function () use ($transfer): ReserveTransfer {
            $locked = ReserveTransfer::query()->lockForUpdate()->findOrFail($transfer->getKey());
            if ($locked->status !== 'submitting') {
                return $locked;
            }
            if ($locked->submitted_at === null || $locked->submitted_at->isAfter(now()->subMinutes(10))) {
                throw new RuntimeException('The bank submission may still be in progress.');
            }
            $this->assertFrozen($locked);
            $this->assertHold($locked);
            app(ReserveIssuanceSnapshot::class)->assertFeeHold($locked);
            $locked->forceFill(['status' => 'uncertain'])->save();
            app(ReserveTransferJournal::class)->recordInterruptedSubmission($locked);

            return $locked;
        }, attempts: 3);
    }

    public function check(ReserveTransfer $transfer, AccountTransferProvider $provider): ReserveTransfer
    {
        $current = $transfer->fresh();
        if ($current->provider !== $provider->providerCode() || $current->status !== 'pending' || ! is_string($current->provider_transaction_id)) {
            throw new RuntimeException('The transfer is not ready for a provider status check.');
        }
        try {
            $result = $provider->checkStatus($current->provider_transaction_id);
        } catch (Throwable) {
            $result = new AccountTransferResultData(AccountTransferStatus::Uncertain, $current->provider_transaction_id);
        }

        return DB::transaction(function () use ($current, $result): ReserveTransfer {
            $locked = ReserveTransfer::query()->lockForUpdate()->findOrFail($current->getKey());
            if ($locked->status !== 'pending' || $locked->provider_transaction_id !== $current->provider_transaction_id) {
                return $locked;
            }
            if ($result->providerTransactionId !== $locked->provider_transaction_id) {
                return $locked;
            }
            $this->assertFrozen($locked);
            $this->assertHold($locked);
            app(ReserveIssuanceSnapshot::class)->assertFeeHold($locked);
            if ($result->status === AccountTransferStatus::Settled) {
                $evidence = $result->evidence;
                $request = AccountTransferRequestData::from($locked->request_ciphertext);
                if (! $evidence instanceof AccountTransferEvidenceData || ! $this->evidenceMatches($request, $evidence)) {
                    $locked->forceFill(['status' => 'review_required', 'bank_evidence_ciphertext' => $evidence?->toArray()])->save();

                    return $locked;
                }
                $locked->forceFill(['status' => 'provider_settled', 'provider_settled_at' => now(), 'bank_evidence_ciphertext' => $evidence->toArray()])->save();
                app(ReserveTransferJournal::class)->recordBankSettlement($locked);
            } elseif ($result->status === AccountTransferStatus::Rejected) {
                $evidence = $result->evidence;
                $request = AccountTransferRequestData::from($locked->request_ciphertext);
                $locked->forceFill([
                    'status' => $evidence instanceof AccountTransferEvidenceData && $this->evidenceMatches($request, $evidence) ? 'rejected' : 'review_required',
                    'bank_evidence_ciphertext' => $evidence?->toArray(),
                ])->save();
            }

            return $locked;
        }, attempts: 3);
    }

    private function evidenceMatches(AccountTransferRequestData $request, AccountTransferEvidenceData $evidence): bool
    {
        $source = preg_replace('/\D+/', '', $evidence->sourceAccountNumber);
        $destination = preg_replace('/\D+/', '', $evidence->destinationAccountNumber);

        return $source !== '' && $destination !== ''
            && hash_equals((string) preg_replace('/\D+/', '', $request->sourceAccountNumber), (string) $source)
            && hash_equals((string) preg_replace('/\D+/', '', $request->destinationAccountNumber), (string) $destination)
            && hash_equals($request->sourceCustomerId, $evidence->sourceCustomerId)
            && hash_equals($request->reference, $evidence->reference)
            && $request->amountMinor === $evidence->amountMinor
            && $request->currency === $evidence->currency
            && $evidence->rail === 'DIRECT' && $evidence->feeMinor === 0;
    }

    public function assertFrozen(ReserveTransfer $transfer): void
    {
        $payload = $transfer->request_ciphertext;
        if (! is_array($payload)) {
            throw new RuntimeException('The frozen transfer request is unavailable.');
        }
        $request = AccountTransferRequestData::from($payload);
        $fingerprint = hash('sha256', json_encode([$payload, $transfer->source_treasury_connection, $transfer->reserve_treasury_connection], JSON_THROW_ON_ERROR));
        if (! hash_equals($transfer->request_fingerprint, $fingerprint)
            || ! hash_equals($transfer->idempotency_hash, hash('sha256', $request->idempotencyKey))
            || $request->reference !== $transfer->reference || $request->provider !== $transfer->provider
            || $request->amountMinor !== $transfer->amount_minor || $request->currency !== $transfer->currency) {
            throw new RuntimeException('The frozen transfer request has changed.');
        }
    }

    public function assertHold(ReserveTransfer $transfer): void
    {
        $expected = 'reserve-transfer-hold:'.hash('sha256', $transfer->reference);
        $operation = TreasuryPositionOperation::query()->with(['sourcePosition', 'destinationPosition'])
            ->where('operation_reference', $transfer->hold_operation_reference)->first();
        $source = $operation?->sourcePosition;
        $destination = $operation?->destinationPosition;
        if (! $operation || ! $source || ! $destination || ! hash_equals($expected, (string) $transfer->hold_operation_reference)
            || $operation->operation_type !== TreasuryPositionOperationType::Reservation || $operation->status !== 'committed'
            || $operation->amount_minor !== $transfer->amount_minor || $operation->currency !== $transfer->currency
            || $operation->external_reference !== 'reserve-transfer:'.$transfer->reference
            || $source->purpose !== TreasuryPositionPurpose::ClientFunds || $destination->purpose !== TreasuryPositionPurpose::PayCodeReserve
            || $source->connection_reference !== $transfer->source_treasury_connection
            || $destination->connection_reference !== $transfer->source_treasury_connection
            || $source->principal_type !== $transfer->owner_type || $destination->principal_type !== $transfer->owner_type
            || (string) $source->principal_id !== (string) $transfer->owner_id
            || (string) $destination->principal_id !== (string) $transfer->owner_id) {
            throw new RuntimeException('The frozen Treasury hold is invalid.');
        }
    }
}
