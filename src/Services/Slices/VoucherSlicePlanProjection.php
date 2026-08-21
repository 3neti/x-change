<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Slices;

use Illuminate\Support\Carbon;
use LBHurtado\Voucher\Data\VoucherSliceData;
use LBHurtado\Voucher\Data\VoucherSlicePlanData;
use LBHurtado\Voucher\Enums\VoucherSlicePlanMode;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Enums\VoucherSliceExecutionStatus;
use LBHurtado\XChange\Models\VoucherSliceExecution;
use LBHurtado\XChange\Models\VoucherSliceExecutionItem;

final readonly class VoucherSlicePlanProjection
{
    public function __construct(private VoucherSliceExecutionCoordinator $executions) {}

    /** @return array<string, mixed> */
    public function forVoucher(Voucher $voucher): array
    {
        $plan = $this->executions->plan($voucher);

        if ($plan === null) {
            return [];
        }

        $executions = VoucherSliceExecution::query()
            ->where('voucher_id', $voucher->getKey())
            ->with(['items', 'claim:id,completed_at'])
            ->orderBy('claim_number')
            ->get();
        $items = $executions->flatMap(function (VoucherSliceExecution $execution) {
            return $execution->items->each(
                static fn (VoucherSliceExecutionItem $item) => $item->setRelation('execution', $execution),
            );
        })->keyBy('slice_id');
        $consumedMinor = (int) $executions
            ->filter(static fn (VoucherSliceExecution $execution): bool => $execution->status === VoucherSliceExecutionStatus::Succeeded)
            ->sum('amount_minor');
        $reservedMinor = (int) $executions
            ->filter(static fn (VoucherSliceExecution $execution): bool => in_array(
                $execution->status,
                [
                    VoucherSliceExecutionStatus::Reserved,
                    VoucherSliceExecutionStatus::Executing,
                    VoucherSliceExecutionStatus::Indeterminate,
                ],
                true,
            ))
            ->sum('amount_minor');
        $claimsUsed = $plan->mode === VoucherSlicePlanMode::Flexible
            ? $executions->filter(static fn (VoucherSliceExecution $execution): bool => in_array(
                $execution->status,
                [
                    VoucherSliceExecutionStatus::Reserved,
                    VoucherSliceExecutionStatus::Executing,
                    VoucherSliceExecutionStatus::Succeeded,
                    VoucherSliceExecutionStatus::Indeterminate,
                ],
                true,
            ))->count()
            : null;
        $claimsRemaining = $claimsUsed === null
            ? null
            : max(0, (int) $plan->max_slices - $claimsUsed);
        $availableMinor = max(0, $plan->total_minor - $consumedMinor - $reservedMinor);
        $rows = $plan->mode === VoucherSlicePlanMode::Flexible
            ? $this->flexibleRows(
                $plan,
                $executions->all(),
                $availableMinor,
                $claimsUsed ?? 0,
                $claimsRemaining ?? 0,
            )
            : $plan->slices->toCollection()->map(
                fn (VoucherSliceData $slice): array => $this->predefinedRow($slice, $items->get($slice->id)),
            )->values()->all();

        return [
            'schema' => 'x-change.voucher-slice-plan-projection.v1',
            'mode' => $plan->mode->value,
            'mode_label' => match ($plan->mode) {
                VoucherSlicePlanMode::Equal => 'Equal',
                VoucherSlicePlanMode::Flexible => 'Flexible',
                VoucherSlicePlanMode::Scheduled => 'Scheduled',
            },
            'selection' => $plan->selection->value,
            'currency' => $plan->currency,
            'total_minor' => $plan->total_minor,
            'consumed_minor' => $consumedMinor,
            'reserved_minor' => $reservedMinor,
            'available_minor' => $availableMinor,
            'slice_count' => $plan->slices->count(),
            'max_slices' => $plan->max_slices,
            'min_amount_minor' => $plan->min_amount_minor,
            'claims_used' => $claimsUsed,
            'claims_remaining' => $claimsRemaining,
            'is_final_claim' => $claimsRemaining === 1 && $availableMinor > 0,
            'rows' => $rows,
            'raw_payload_exposed' => false,
        ];
    }

    /** @return array<string, mixed> */
    private function predefinedRow(VoucherSliceData $slice, ?VoucherSliceExecutionItem $item): array
    {
        $status = $item?->status ?? $this->windowStatus($slice);

        return [
            'id' => $slice->id,
            'label' => $slice->label,
            'sequence' => $slice->sequence,
            'amount_minor' => $slice->amount_minor,
            'status' => $status,
            'status_label' => match ($status) {
                'consumed' => 'Paid',
                'reserved' => 'In progress',
                'scheduled' => 'Scheduled',
                'expired' => 'Expired',
                default => 'Available',
            },
            'claim_on' => $slice->claim_on,
            'claim_by' => $slice->claim_by,
            'claim_number' => $item?->execution?->claim_number,
            'claimed_at' => $this->claimedAt($item?->execution),
        ];
    }

    /** @param array<int, VoucherSliceExecution> $executions @return array<int, array<string, mixed>> */
    private function flexibleRows(
        VoucherSlicePlanData $plan,
        array $executions,
        int $availableMinor,
        int $claimsUsed,
        int $claimsRemaining,
    ): array {
        $rows = collect($executions)->map(fn (VoucherSliceExecution $execution): array => [
            'id' => $execution->reference,
            'label' => 'Claim '.$execution->claim_number,
            'sequence' => $execution->claim_number,
            'amount_minor' => $execution->amount_minor,
            'status' => $execution->status->value,
            'status_label' => match ($execution->status->value) {
                'succeeded' => 'Paid',
                'indeterminate' => 'Needs review',
                'failed' => 'Failed',
                default => 'In progress',
            },
            'claim_on' => null,
            'claim_by' => null,
            'claim_number' => $execution->claim_number,
            'claimed_at' => $this->claimedAt($execution),
        ])->all();
        if ($availableMinor > 0 && $claimsRemaining > 0) {
            $rows[] = [
                'id' => 'remaining_capacity',
                'label' => 'Remaining capacity',
                'sequence' => count($rows) + 1,
                'amount_minor' => $availableMinor,
                'status' => 'available',
                'status_label' => 'Available',
                'claim_on' => null,
                'claim_by' => null,
                'claim_number' => null,
                'claimed_at' => null,
                'max_slices' => (int) $plan->max_slices,
                'min_amount_minor' => (int) $plan->min_amount_minor,
                'claims_used' => $claimsUsed,
                'claims_remaining' => $claimsRemaining,
                'is_final_claim' => $claimsRemaining === 1,
            ];
        }

        return $rows;
    }

    private function claimedAt(?VoucherSliceExecution $execution): ?string
    {
        if ($execution?->status !== VoucherSliceExecutionStatus::Succeeded) {
            return null;
        }

        return ($execution->settled_at ?? $execution->claim?->completed_at)?->toIso8601String();
    }

    private function windowStatus(VoucherSliceData $slice): string
    {
        $now = Carbon::now();

        if ($slice->claim_on !== null && $now->lt(Carbon::parse($slice->claim_on))) {
            return 'scheduled';
        }

        if ($slice->claim_by !== null && $now->gt(Carbon::parse($slice->claim_by))) {
            return 'expired';
        }

        return 'available';
    }
}
