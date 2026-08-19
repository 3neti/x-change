<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Treasury;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryAllocationOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationReleaseRequestData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Data\Treasury\PayCodeTerminalReleaseData;
use LBHurtado\XChange\Events\FundingProjectionChanged;
use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;
use LBHurtado\XChange\Models\StoredValueHolderBinding;
use LBHurtado\XChange\Services\Treasury\PayCodeTerminalReleaseJournal;
use LBHurtado\XChange\Services\Treasury\TreasuryPayCodeAccountingService;

final readonly class ReleasePayCodeTerminalReserve
{
    public function __construct(
        private TreasuryPayCodeAccountingService $accounting,
        private PayCodeTerminalReleaseJournal $journal,
        private TreasuryAllocationOperationContract $allocationOperations,
    ) {}

    public function handle(
        Voucher $voucher,
        string $terminalReason,
    ): PayCodeTerminalReleaseData {
        return DB::transaction(
            function () use ($terminalReason, $voucher): PayCodeTerminalReleaseData {
                Voucher::query()
                    ->whereKey($voucher->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                $voucher->refresh();

                return $this->handleLocked($voucher, $terminalReason);
            },
            attempts: 5,
        );
    }

    private function handleLocked(
        Voucher $voucher,
        string $terminalReason,
    ): PayCodeTerminalReleaseData {
        $reason = mb_strtolower(trim($terminalReason));

        if (! in_array($reason, ['cancelled', 'expired'], true)) {
            throw new TreasuryConfigurationException(
                "Unsupported Pay Code terminal release reason [{$terminalReason}].",
            );
        }

        $reservation = data_get(
            is_array($voucher->metadata) ? $voucher->metadata : [],
            'treasury.pay_code_reservation',
        );

        if (! is_array($reservation)) {
            return new PayCodeTerminalReleaseData(
                status: 'not_applicable',
                terminalReason: $reason,
                operationReference: null,
                amountMinor: 0,
                currency: null,
            );
        }

        $existingRelease = data_get(
            is_array($voucher->metadata) ? $voucher->metadata : [],
            'treasury.terminal_release',
        );

        if (is_array($existingRelease)) {
            return new PayCodeTerminalReleaseData(
                status: (string) data_get($existingRelease, 'status', 'released'),
                terminalReason: (string) data_get(
                    $existingRelease,
                    'terminal_reason',
                    $reason,
                ),
                operationReference: $this->nullableString(
                    data_get($existingRelease, 'operation_reference'),
                ),
                amountMinor: (int) data_get($existingRelease, 'amount_minor', 0),
                currency: $this->nullableString(
                    data_get($existingRelease, 'currency'),
                ),
                replayed: true,
            );
        }

        $storedValueBinding = StoredValueHolderBinding::query()
            ->where('voucher_id', $voucher->getKey())
            ->where('status', 'active')
            ->lockForUpdate()
            ->first();

        if ($storedValueBinding instanceof StoredValueHolderBinding) {
            if ($reason === 'cancelled') {
                throw new TreasuryConfigurationException(
                    'An activated Reusable Balance requires a governed revocation instead of Pay Code cancellation.',
                );
            }

            return $this->releaseStoredValue(
                $voucher,
                $storedValueBinding,
                $reservation,
            );
        }

        $sourcePurpose = (string) data_get(
            $reservation,
            'source_position_purpose',
            TreasuryPositionPurpose::ClientFunds->value,
        );

        if ($sourcePurpose !== TreasuryPositionPurpose::ClientFunds->value) {
            $reasonLabel = $reason === 'cancelled' ? 'cancellation' : 'expiry';

            throw new TreasuryConfigurationException(
                "Pay Code [{$voucher->code}] was reserved from [{$sourcePurpose}]; "
                ."{$reasonLabel} cannot return it to Client Funds.",
            );
        }

        $owner = $voucher->owner;

        if (! $owner instanceof Model) {
            throw new TreasuryConfigurationException(
                "Pay Code [{$voucher->code}] has no Account owner for reserve release.",
            );
        }

        $connectionReference = trim((string) data_get(
            $reservation,
            'connection_reference',
        ));
        $amountMinor = (int) data_get($reservation, 'amount_minor', 0);
        $currency = mb_strtoupper(trim((string) data_get(
            $reservation,
            'currency',
        )));

        if ($connectionReference === '' || $amountMinor <= 0 || $currency === '') {
            throw new TreasuryConfigurationException(
                "Pay Code [{$voucher->code}] has an incomplete Treasury reservation.",
            );
        }

        $reasonReference = "pay-code-terminal:{$reason}:{$voucher->getKey()}";
        $release = $this->accounting->release(
            accountOwner: $owner,
            voucher: $voucher,
            connectionReference: $connectionReference,
            providerPrincipalMinor: $amountMinor,
            currency: $currency,
            reasonReference: $reasonReference,
        );
        $result = new PayCodeTerminalReleaseData(
            status: 'released',
            terminalReason: $reason,
            operationReference: $release->operationReference,
            amountMinor: $amountMinor,
            currency: $currency,
        );
        $metadata = is_array($voucher->metadata) ? $voucher->metadata : [];
        data_set($metadata, 'treasury.pay_code_reservation.status', 'released');
        data_set($metadata, 'treasury.terminal_release', [
            'schema' => 'x-change.pay-code-terminal-release.v1',
            ...$result->toArray(),
            'released_at' => now()->toIso8601String(),
        ]);
        $voucher->forceFill(['metadata' => $metadata])->saveQuietly();
        $reservationOperationReference = $this->nullableString(
            data_get($reservation, 'operation_reference'),
        );

        DB::afterCommit(function () use (
            $owner,
            $reservationOperationReference,
            $result,
            $voucher,
        ): void {
            $this->journal->record(
                $voucher,
                $owner,
                $result,
                $reservationOperationReference,
            );
            FundingProjectionChanged::dispatch(
                $owner::class,
                (string) $owner->getKey(),
                (string) $result->operationReference,
                now()->toIso8601String(),
                'pay_code_reserve_released',
            );
        });

        return $result;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @param  array<string, mixed>  $reservation
     */
    private function releaseStoredValue(
        Voucher $voucher,
        StoredValueHolderBinding $binding,
        array $reservation,
    ): PayCodeTerminalReleaseData {
        $scope = hash('sha256', implode('|', [
            'x-change.stored-value-expiry.v1',
            $binding->allocation_reference,
            (string) $voucher->getKey(),
        ]));
        $operationReference = 'stored-value-expiry-release:'.$scope;
        $release = $this->allocationOperations->release(
            new TreasuryAllocationReleaseRequestData(
                operationReference: $operationReference,
                allocationReference: $binding->allocation_reference,
                currency: $binding->currency,
                idempotencyKey: $operationReference,
                externalReference: 'stored-value-voucher:'.$voucher->getKey(),
                metadata: [
                    'source' => 'x_change_stored_value_expiry',
                    'holder_authority_reference' => $binding->holder_authority_reference,
                ],
            ),
        );
        $result = new PayCodeTerminalReleaseData(
            status: 'released',
            terminalReason: 'expired',
            operationReference: $release->operationReference,
            amountMinor: $release->amountMinor,
            currency: $release->currency,
        );
        $metadata = is_array($voucher->metadata) ? $voucher->metadata : [];
        data_set($metadata, 'treasury.pay_code_reservation.status', 'released');
        data_set($metadata, 'treasury.terminal_release', [
            'schema' => 'x-change.pay-code-terminal-release.v1',
            ...$result->toArray(),
            'release_source' => 'stored_value_allocation_remainder',
            'released_at' => now()->toIso8601String(),
        ]);
        $voucher->forceFill(['metadata' => $metadata])->saveQuietly();
        StoredValueHolderBinding::query()
            ->whereKey($binding->getKey())
            ->where('status', 'active')
            ->update([
                'status' => 'released',
                'released_at' => now(),
                'updated_at' => now(),
            ]);
        $owner = $voucher->owner;

        if ($owner instanceof Model) {
            DB::afterCommit(function () use ($owner, $reservation, $result, $voucher): void {
                $this->journal->record(
                    $voucher,
                    $owner,
                    $result,
                    $this->nullableString(data_get($reservation, 'operation_reference')),
                );
                FundingProjectionChanged::dispatch(
                    $owner::class,
                    (string) $owner->getKey(),
                    (string) $result->operationReference,
                    now()->toIso8601String(),
                    'stored_value_remainder_released',
                );
            });
        }

        return $result;
    }
}
