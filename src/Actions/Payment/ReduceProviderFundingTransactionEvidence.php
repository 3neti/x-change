<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Payment;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\XChange\Data\Payment\CanonicalProviderFundingTransactionData;
use LBHurtado\XChange\Exceptions\IncompatibleProviderFundingEvidence;

final class ReduceProviderFundingTransactionEvidence
{
    /** @var array<string, list<string>> */
    private const AllowedStatusTransitions = [
        'pending' => ['processing', 'settled'],
        'processing' => ['settled'],
    ];

    public function forObservation(
        ProviderFundingObservation $observation,
    ): CanonicalProviderFundingTransactionData {
        return $this->handle(
            ProviderFundingObservation::query()
                ->where('provider_code', $observation->provider_code)
                ->where('provider_transaction_id', $observation->provider_transaction_id)
                ->orderBy('id')
                ->get(),
        );
    }

    /**
     * @param  iterable<ProviderFundingObservation>  $observations
     */
    public function handle(iterable $observations): CanonicalProviderFundingTransactionData
    {
        $evidence = collect($observations)
            ->each(function (mixed $observation): void {
                if (! $observation instanceof ProviderFundingObservation
                    || ! $observation->exists
                    || (int) $observation->getKey() < 1) {
                    throw new InvalidArgumentException(
                        'Canonical provider funding evidence requires persisted observations.',
                    );
                }
            })
            ->sortBy(fn (ProviderFundingObservation $observation): int => (int) $observation->getKey())
            ->values();

        if ($evidence->isEmpty()) {
            throw new InvalidArgumentException('At least one provider funding observation is required.');
        }

        /** @var ProviderFundingObservation $first */
        $first = $evidence->first();
        $providerCode = strtolower(trim((string) $first->provider_code));
        $providerTransactionId = trim((string) $first->provider_transaction_id);

        if ($providerCode === '' || $providerTransactionId === '') {
            throw new InvalidArgumentException(
                'Provider funding evidence requires provider and transaction identity.',
            );
        }

        $transactionKey = hash('sha256', implode("\0", [
            $providerCode,
            $providerTransactionId,
        ]));
        $canonicalStatus = strtolower(trim((string) $first->provider_status));

        if ($canonicalStatus === '') {
            throw new InvalidArgumentException('Provider funding evidence requires a status.');
        }

        if ((int) $first->gross_amount_minor < 0
            || (int) $first->fee_amount_minor < 0
            || (int) $first->net_amount_minor < 0
            || strlen(trim((string) $first->currency)) !== 3) {
            throw new InvalidArgumentException(
                'Provider funding evidence requires valid amounts and currency.',
            );
        }

        $providerOperationId = $this->stableOptionalValue(
            $evidence,
            'provider_operation_id',
            $transactionKey,
        );
        $requestId = $this->stableOptionalValue(
            $evidence,
            'request_id',
            $transactionKey,
        );
        $occurredAt = $this->stableOccurredAt($evidence, $transactionKey);
        $destinationVerified = data_get($first->metadata, 'destination_verified');
        $settledAt = null;

        foreach ($evidence as $observation) {
            $this->assertSame($transactionKey, 'provider_code', $providerCode, strtolower(trim(
                (string) $observation->provider_code,
            )));
            $this->assertSame(
                $transactionKey,
                'provider_transaction_id',
                $providerTransactionId,
                trim((string) $observation->provider_transaction_id),
            );
            $this->assertSame(
                $transactionKey,
                'gross_amount_minor',
                (int) $first->gross_amount_minor,
                (int) $observation->gross_amount_minor,
            );
            $this->assertSame(
                $transactionKey,
                'fee_amount_minor',
                (int) $first->fee_amount_minor,
                (int) $observation->fee_amount_minor,
            );
            $this->assertSame(
                $transactionKey,
                'net_amount_minor',
                (int) $first->net_amount_minor,
                (int) $observation->net_amount_minor,
            );
            $this->assertSame(
                $transactionKey,
                'currency',
                strtoupper(trim((string) $first->currency)),
                strtoupper(trim((string) $observation->currency)),
            );
            $this->assertSame(
                $transactionKey,
                'funding_address',
                $first->funding_address,
                $observation->funding_address,
            );
            $this->assertSame(
                $transactionKey,
                'provider_account_reference',
                $first->provider_account_reference,
                $observation->provider_account_reference,
            );
            $this->assertSame(
                $transactionKey,
                'destination_verified',
                $destinationVerified,
                data_get($observation->metadata, 'destination_verified'),
            );

            $nextSettledAt = $observation->settledAtInstant();

            if ($settledAt instanceof CarbonImmutable
                && $nextSettledAt instanceof CarbonImmutable
                && $nextSettledAt->lessThan($settledAt)) {
                throw IncompatibleProviderFundingEvidence::forField(
                    $transactionKey,
                    'settled_at_regression',
                );
            }

            $settledAt = $nextSettledAt ?? $settledAt;

            $nextStatus = strtolower(trim((string) $observation->provider_status));

            if ($nextStatus === '') {
                throw new InvalidArgumentException('Provider funding evidence requires a status.');
            }

            if ($nextStatus !== $canonicalStatus) {
                if (! in_array(
                    $nextStatus,
                    self::AllowedStatusTransitions[$canonicalStatus] ?? [],
                    true,
                )) {
                    throw IncompatibleProviderFundingEvidence::forStatusTransition(
                        $transactionKey,
                        $canonicalStatus,
                        $nextStatus,
                    );
                }

                $canonicalStatus = $nextStatus;
            }
        }

        /** @var ProviderFundingObservation $canonical */
        $canonical = $evidence->last();
        $settlementRail = $this->stableMetadataValue(
            $evidence,
            'settlement_rail',
            $transactionKey,
        );

        return new CanonicalProviderFundingTransactionData(
            providerCode: $providerCode,
            providerTransactionKey: $transactionKey,
            providerOperationKey: $this->identityKey($providerCode, $providerOperationId),
            requestKey: $this->identityKey($providerCode, $requestId),
            fundingAddressFingerprint: $first->funding_address,
            providerAccountFingerprint: $first->provider_account_reference,
            grossAmountMinor: (int) $first->gross_amount_minor,
            feeAmountMinor: (int) $first->fee_amount_minor,
            netAmountMinor: (int) $first->net_amount_minor,
            currency: strtoupper(trim((string) $first->currency)),
            providerStatus: $canonicalStatus,
            occurredAt: $occurredAt,
            settledAt: $settledAt,
            settlementRail: $settlementRail === null ? null : strtoupper($settlementRail),
            destinationVerified: $destinationVerified === true,
            canonicalObservationId: (int) $canonical->getKey(),
            evidenceObservationIds: $evidence
                ->map(fn (ProviderFundingObservation $observation): int => (int) $observation->getKey())
                ->all(),
            verificationSources: $this->uniqueStrings($evidence, 'verification_source'),
            normalizationVersions: $evidence
                ->map(fn (ProviderFundingObservation $observation): string => trim((string) data_get(
                    $observation->metadata,
                    'normalization_version',
                )))
                ->filter()
                ->unique()
                ->values()
                ->all(),
        );
    }

    /**
     * @param  Collection<int, ProviderFundingObservation>  $evidence
     */
    private function stableOptionalValue(
        Collection $evidence,
        string $field,
        string $transactionKey,
    ): ?string {
        $values = $this->uniqueStrings($evidence, $field);

        if (count($values) > 1) {
            throw IncompatibleProviderFundingEvidence::forField($transactionKey, $field);
        }

        return $values[0] ?? null;
    }

    /**
     * @param  Collection<int, ProviderFundingObservation>  $evidence
     */
    private function stableMetadataValue(
        Collection $evidence,
        string $field,
        string $transactionKey,
    ): ?string {
        $values = $evidence
            ->map(fn (ProviderFundingObservation $observation): string => trim((string) data_get(
                $observation->metadata,
                $field,
            )))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (count($values) > 1) {
            throw IncompatibleProviderFundingEvidence::forField($transactionKey, $field);
        }

        return $values[0] ?? null;
    }

    /**
     * @param  Collection<int, ProviderFundingObservation>  $evidence
     */
    private function stableOccurredAt(
        Collection $evidence,
        string $transactionKey,
    ): ?CarbonImmutable {
        $values = $evidence
            ->map(fn (ProviderFundingObservation $observation): ?CarbonImmutable => $observation->occurredAtInstant())
            ->filter()
            ->unique(fn (CarbonImmutable $instant): string => $instant->toISOString())
            ->values();

        if ($values->count() > 1) {
            throw IncompatibleProviderFundingEvidence::forField($transactionKey, 'occurred_at');
        }

        return $values->first();
    }

    /**
     * @param  Collection<int, ProviderFundingObservation>  $evidence
     * @return list<string>
     */
    private function uniqueStrings(Collection $evidence, string $field): array
    {
        return $evidence
            ->map(fn (ProviderFundingObservation $observation): string => trim((string) $observation->{$field}))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function identityKey(string $providerCode, ?string $value): ?string
    {
        return $value === null ? null : hash('sha256', implode("\0", [
            $providerCode,
            $value,
        ]));
    }

    private function assertSame(
        string $transactionKey,
        string $field,
        mixed $expected,
        mixed $actual,
    ): void {
        if ($expected !== $actual) {
            throw IncompatibleProviderFundingEvidence::forField($transactionKey, $field);
        }
    }
}
