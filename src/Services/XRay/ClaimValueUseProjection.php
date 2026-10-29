<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\XRay;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryAllocationReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationReadModelQueryData;
use LBHurtado\XChange\Models\StoredValueHolderBinding;
use LBHurtado\XChange\Support\Money\MajorCurrencyAmount;
use Throwable;

final readonly class ClaimValueUseProjection
{
    public function __construct(private TreasuryAllocationReadModelContract $allocations) {}

    /** @param array<string, mixed> $slicePlan @return array<string, mixed> */
    public function forVoucher(Voucher $voucher, array $slicePlan): array
    {
        $currency = strtoupper((string) data_get($voucher->instructions, 'cash.currency', 'PHP'));
        $base = ['schema' => 'x-change.claim-value-use.v1', 'currency' => $currency];

        if (data_get($voucher->metadata, 'instructions.execution.driver') === 'stored_value') {
            return [...$base, ...$this->storedValue($voucher, $currency)];
        }

        if ($slicePlan !== []) {
            return [...$base, ...$this->slices($slicePlan)];
        }

        try {
            $amount = MajorCurrencyAmount::toMinor((string) data_get($voucher->instructions, 'cash.amount'), $currency);
        } catch (Throwable) {
            $amount = null;
        }

        return [...$base, 'mode' => 'whole', 'amount_basis' => 'whole', 'amount_minor' => $amount];
    }

    /** @param array<string, mixed> $plan @return array<string, mixed> */
    private function slices(array $plan): array
    {
        $mode = (string) ($plan['mode'] ?? '');
        if ($mode === 'flexible') {
            return [
                'mode' => 'flexible',
                'amount_basis' => 'remaining',
                'amount_minor' => max(0, (int) ($plan['available_minor'] ?? 0)),
                'minimum_minor' => max(0, (int) ($plan['min_amount_minor'] ?? 0)),
                'claims_remaining' => max(0, (int) ($plan['claims_remaining'] ?? 0)),
            ];
        }

        $rows = collect($plan['rows'] ?? []);
        $available = $rows->first(static fn (mixed $row): bool => data_get($row, 'status') === 'available');
        $next = $available ?? ($mode === 'scheduled'
            ? $rows->first(static fn (mixed $row): bool => data_get($row, 'status') === 'scheduled')
            : null);

        return [
            'mode' => $mode === 'scheduled' ? 'scheduled' : 'equal',
            'amount_basis' => $next === null ? 'unavailable' : ($available === null ? 'next_available' : 'available_now'),
            'amount_minor' => $next === null ? null : max(0, (int) data_get($next, 'amount_minor', 0)),
            'portion_number' => $next === null ? null : max(1, (int) data_get($next, 'sequence', 1)),
            'portion_count' => max(0, (int) ($plan['slice_count'] ?? 0)),
            'available_at' => $available === null ? data_get($next, 'claim_on') : null,
        ];
    }

    /** @return array<string, mixed> */
    private function storedValue(Voucher $voucher, string $currency): array
    {
        $terms = data_get($voucher->metadata, 'instructions.execution.metadata.stored_value', []);
        $binding = StoredValueHolderBinding::query()
            ->where('voucher_id', $voucher->getKey())
            ->where('status', 'active')
            ->first();

        if ($binding === null) {
            return [
                'mode' => 'stored_value',
                'amount_basis' => 'starting',
                'amount_minor' => is_numeric(data_get($terms, 'initial_balance'))
                    ? max(0, (int) data_get($terms, 'initial_balance'))
                    : null,
                'collection_terms' => $this->collectionTerms($terms),
            ];
        }

        try {
            $state = $this->allocations->read(new TreasuryAllocationReadModelQueryData(
                allocationReference: $binding->allocation_reference,
                currency: $currency,
            ));
            $balance = $state->hasTreasuryFacts ? max(0, $state->usableAmountMinor) : null;
        } catch (Throwable $exception) {
            report($exception);
            $balance = null;
        }

        return [
            'mode' => 'stored_value',
            'amount_basis' => $balance === null ? 'unavailable' : 'remaining',
            'amount_minor' => $balance,
            'collection_terms' => $this->collectionTerms($terms),
        ];
    }

    /** @return array<string, int|string|null> */
    private function collectionTerms(mixed $terms): array
    {
        $threshold = data_get($terms, 'otp_required_above');

        return [
            'amount' => 'collector_sets_amount',
            'otp_above_minor' => is_numeric($threshold) && (int) $threshold > 0 ? (int) $threshold : null,
        ];
    }
}
