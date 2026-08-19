<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\StoredValue;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryAllocationActivityReadModelContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryAllocationReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationActivityReadModelData;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationActivityReadModelQueryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationReadModelData;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationReadModelQueryData;
use LBHurtado\XChange\Models\StoredValueHolderBinding;
use Throwable;

final readonly class HolderStoredValueReadModel
{
    private const int SUMMARY_LIMIT = 20;

    private const int ACTIVITY_PER_PAGE = 25;

    public function __construct(
        private TreasuryAllocationReadModelContract $allocations,
        private TreasuryAllocationActivityReadModelContract $activity,
    ) {}

    /** @return list<array<string, mixed>> */
    public function summaries(Model $holder): array
    {
        return $this->ownedBindings($holder)
            ->latest('activated_at')
            ->latest('id')
            ->limit(self::SUMMARY_LIMIT)
            ->get()
            ->map(fn (StoredValueHolderBinding $binding): array => $this->summary($binding))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function detail(Model $holder, string $reference, int $page = 1): array
    {
        $binding = $this->ownedBindings($holder)
            ->where('reference', $reference)
            ->firstOrFail();
        $summary = $this->summary($binding);
        $activity = $this->activityState($binding, $page);

        return [
            ...$summary,
            'schema' => 'x-change.holder-stored-value-detail.v1',
            'activity_available' => $activity?->hasTreasuryFacts === true,
            'transactions' => array_map(
                static fn ($movement): array => [
                    'type' => $movement->type,
                    'label' => match ($movement->type) {
                        'activation' => 'Activated',
                        'draw' => 'Purchase',
                        'replenishment' => 'Added funds',
                        'release' => 'Released',
                        'reversal' => 'Reversed',
                        default => 'Balance activity',
                    },
                    'amount_minor' => $movement->balanceAfterMinor - $movement->balanceBeforeMinor,
                    'balance_after_minor' => $movement->balanceAfterMinor,
                    'currency' => strtoupper($movement->currency),
                    'occurred_at' => $movement->effectiveAt,
                ],
                $activity?->movements ?? [],
            ),
            'pagination' => [
                'current_page' => $activity?->currentPage ?? max(1, $page),
                'per_page' => $activity?->perPage ?? self::ACTIVITY_PER_PAGE,
                'total' => $activity?->total ?? 0,
                'last_page' => $activity?->lastPage ?? 1,
            ],
        ];
    }

    /** @return Builder<StoredValueHolderBinding> */
    private function ownedBindings(Model $holder): Builder
    {
        return StoredValueHolderBinding::query()
            ->select([
                'id',
                'reference',
                'voucher_id',
                'allocation_reference',
                'currency',
                'status',
                'activated_at',
                'released_at',
            ])
            ->with(['voucher:id,expires_at'])
            ->where('holder_type', $holder->getMorphClass())
            ->where('holder_id', (string) $holder->getKey());
    }

    /** @return array<string, mixed> */
    private function summary(StoredValueHolderBinding $binding): array
    {
        $state = $this->allocationState($binding);
        $hasFacts = $state?->hasTreasuryFacts === true;
        $available = $hasFacts ? $state->usableAmountMinor : null;
        $maximum = $hasFacts
            ? (int) ($state->metadata['maximum_amount_minor'] ?? $state->allocatedAmountMinor)
            : null;

        return [
            'schema' => 'x-change.holder-stored-value-summary.v1',
            'reference' => $binding->reference,
            'status' => $this->status($binding, $available, $maximum, $hasFacts),
            'currency' => strtoupper($binding->currency),
            'available_minor' => $available,
            'total_loaded_minor' => $hasFacts ? $state->allocatedAmountMinor : null,
            'total_spent_minor' => $hasFacts ? $state->drawnAmountMinor : null,
            'maximum_minor' => $maximum,
            'replenishable' => $hasFacts
                ? (bool) ($state->metadata['replenishable'] ?? false)
                : null,
            'activated_at' => $binding->activated_at?->utc()->toIso8601String(),
            'expires_at' => $binding->voucher?->expires_at?->utc()->toIso8601String(),
        ];
    }

    private function allocationState(
        StoredValueHolderBinding $binding,
    ): ?TreasuryAllocationReadModelData {
        try {
            return $this->allocations->read(new TreasuryAllocationReadModelQueryData(
                allocationReference: $binding->allocation_reference,
                currency: $binding->currency,
            ));
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    private function activityState(
        StoredValueHolderBinding $binding,
        int $page,
    ): ?TreasuryAllocationActivityReadModelData {
        try {
            return $this->activity->read(new TreasuryAllocationActivityReadModelQueryData(
                allocationReference: $binding->allocation_reference,
                currency: $binding->currency,
                page: max(1, $page),
                perPage: self::ACTIVITY_PER_PAGE,
            ));
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    private function status(
        StoredValueHolderBinding $binding,
        ?int $available,
        ?int $maximum,
        bool $hasFacts,
    ): string {
        if (! $hasFacts) {
            return 'unavailable';
        }

        if ($binding->released_at !== null || $binding->status !== 'active') {
            return 'closed';
        }

        if ($binding->voucher?->expires_at?->isPast() === true) {
            return 'expired';
        }

        if ($available === 0) {
            return 'depleted';
        }

        if ($maximum !== null && $maximum > 0 && $available !== null && $available * 5 <= $maximum) {
            return 'low_balance';
        }

        return 'active';
    }
}
