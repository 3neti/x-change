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
    public function __construct(private VoucherSliceExecutionCoordinator $executions)
    {
    }

    /** @return array<string, mixed> */
    public function forVoucher(Voucher $voucher): array
    {
        $plan = $this->executions->plan($voucher);

        if ($plan === null) {
            return [];
        }

        $executions = VoucherSliceExecution::query()
            ->where('voucher_id', $voucher->getKey())
            ->with('items')
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
        $rows = $plan->mode === VoucherSlicePlanMode::Flexible
            ? $this->flexibleRows($plan, $executions->all(), $consumedMinor, $reservedMinor)
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
            'available_minor' => max(0, $plan->total_minor - $consumedMinor - $reservedMinor),
            'slice_count' => $plan->slices->count(),
            'max_slices' => $plan->max_slices,
            'min_amount_minor' => $plan->min_amount_minor,
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
        ];
    }

    /** @param array<int, VoucherSliceExecution> $executions @return array<int, array<string, mixed>> */
    private function flexibleRows(VoucherSlicePlanData $plan, array $executions, int $consumedMinor, int $reservedMinor): array
    {
        $rows = collect($executions)->map(static fn (VoucherSliceExecution $execution): array => [
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
        ])->all();
        $available = max(0, $plan->total_minor - $consumedMinor - $reservedMinor);

        if ($available > 0 && count($executions) < (int) $plan->max_slices) {
            $rows[] = [
                'id' => 'remaining_capacity',
                'label' => 'Remaining capacity',
                'sequence' => count($rows) + 1,
                'amount_minor' => $available,
                'status' => 'available',
                'status_label' => 'Available',
                'claim_on' => null,
                'claim_by' => null,
                'claim_number' => null,
            ];
        }

        return $rows;
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
