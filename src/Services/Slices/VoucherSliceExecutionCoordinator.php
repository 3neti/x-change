<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Slices;

use Brick\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LBHurtado\Voucher\Data\VoucherSliceData;
use LBHurtado\Voucher\Data\VoucherSlicePlanData;
use LBHurtado\Voucher\Enums\VoucherSlicePlanMode;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use LBHurtado\XChange\Data\Redemption\VoucherSliceReservationData;
use LBHurtado\XChange\Enums\VoucherSliceExecutionStatus;
use LBHurtado\XChange\Exceptions\VoucherSliceExecutionConflict;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Models\VoucherSliceExecution;
use LBHurtado\XChange\Models\VoucherSliceExecutionItem;
use LBHurtado\XChange\Models\VoucherSliceExecutionOutbox;
use Throwable;

final readonly class VoucherSliceExecutionCoordinator
{
    public function __construct(private VoucherSliceExecutionJournal $journal)
    {
    }

    /** @param array<string, mixed> $payload */
    public function reserve(Voucher $voucher, array $payload): ?VoucherSliceReservationData
    {
        $plan = $this->plan($voucher);

        if ($plan === null) {
            return null;
        }

        $idempotencyKey = trim((string) data_get($payload, '_meta.idempotency_key', ''));

        if ($idempotencyKey === '') {
            throw ValidationException::withMessages([
                'idempotency_key' => 'A stable idempotency key is required for slice claims.',
            ]);
        }

        $idempotencyHash = hash('sha256', $idempotencyKey);
        $result = DB::transaction(function () use ($voucher, $payload, $plan, $idempotencyHash): VoucherSliceReservationData {
            $lockedVoucher = Voucher::query()->lockForUpdate()->findOrFail($voucher->getKey());
            $existing = VoucherSliceExecution::query()
                ->where('voucher_id', $lockedVoucher->getKey())
                ->where('idempotency_key_hash', $idempotencyHash)
                ->with(['items', 'claim'])
                ->first();

            if ($existing instanceof VoucherSliceExecution) {
                $selected = $existing->items->map(static fn (VoucherSliceExecutionItem $item): array => [
                    'id' => $item->slice_id,
                    'label' => $item->label,
                    'sequence' => $item->sequence,
                    'amount_minor' => $item->amount_minor,
                ])->all();
                $requestFingerprint = $this->requestFingerprint(
                    $lockedVoucher,
                    $plan,
                    $selected,
                    $existing->amount_minor,
                    $payload,
                );

                if (! hash_equals($existing->request_fingerprint, $requestFingerprint)) {
                    throw new VoucherSliceExecutionConflict('The slice idempotency key was already used with different claim facts.');
                }

                return new VoucherSliceReservationData(
                    payload: $this->enrichPayload($payload, $existing),
                    execution: $existing,
                    replayed: true,
                );
            }

            [$selected, $amountMinor] = $this->selection($lockedVoucher, $plan, $payload);
            $requestFingerprint = $this->requestFingerprint($lockedVoucher, $plan, $selected, $amountMinor, $payload);

            $reference = (string) Str::ulid();
            $preparedClaim = VoucherClaim::query()
                ->where('voucher_id', $lockedVoucher->getKey())
                ->whereKey(data_get($payload, '_meta.prepared_claim_id'))
                ->first();
            $claimNumber = $preparedClaim?->claim_number ?? (max(
                (int) VoucherClaim::query()->where('voucher_id', $lockedVoucher->getKey())->max('claim_number'),
                (int) VoucherSliceExecution::query()->where('voucher_id', $lockedVoucher->getKey())->max('claim_number'),
            ) + 1);
            $now = now();

            $execution = VoucherSliceExecution::query()->create([
                'reference' => $reference,
                'voucher_id' => $lockedVoucher->getKey(),
                'plan_fingerprint' => $plan->hash(),
                'idempotency_key_hash' => $idempotencyHash,
                'request_fingerprint' => $requestFingerprint,
                'provider_operation_reference' => 'slice-'.$reference,
                'claim_number' => $claimNumber,
                'amount_minor' => $amountMinor,
                'currency' => $plan->currency,
                'reserved_at' => $now,
                'metadata' => [
                    'schema' => 'x-change.voucher-slice-execution.v1',
                    'mode' => $plan->mode->value,
                ],
            ]);

            foreach ($selected as $slice) {
                VoucherSliceExecutionItem::query()->create([
                    'execution_id' => $execution->getKey(),
                    'voucher_id' => $lockedVoucher->getKey(),
                    'slice_id' => $slice['id'],
                    'label' => $slice['label'],
                    'sequence' => $slice['sequence'],
                    'amount_minor' => $slice['amount_minor'],
                    'reserved_at' => $now,
                ]);
            }

            $this->appendEvent($execution, 'voucher.slice.reserved', 'reserved', false);
            $execution->setRelation('items', $execution->items()->get());

            return new VoucherSliceReservationData(
                payload: $this->enrichPayload($payload, $execution),
                execution: $execution,
                replayed: false,
            );
        }, attempts: 5);

        $this->journal->deliverForExecution($result->execution->getKey());

        return $result;
    }

    public function begin(VoucherSliceExecution $execution): void
    {
        DB::transaction(function () use ($execution): void {
            $locked = VoucherSliceExecution::query()->lockForUpdate()->findOrFail($execution->getKey());

            if ($locked->status !== VoucherSliceExecutionStatus::Reserved) {
                return;
            }

            $locked->forceFill([
                'status' => VoucherSliceExecutionStatus::Executing,
                'executing_at' => now(),
                'version' => $locked->version + 1,
            ])->save();
            $this->appendEvent($locked, 'voucher.slice.execution_started', 'executing', true);
        }, attempts: 5);

        $this->journal->deliverForExecution($execution->getKey());
    }

    public function succeed(VoucherSliceExecution $execution, VoucherClaim $claim): void
    {
        DB::transaction(function () use ($execution, $claim): void {
            $locked = VoucherSliceExecution::query()->lockForUpdate()->findOrFail($execution->getKey());

            if ($locked->status === VoucherSliceExecutionStatus::Succeeded) {
                return;
            }

            $now = now();
            $locked->items()->where('status', 'reserved')->update([
                'status' => 'consumed',
                'consumed_at' => $now,
                'updated_at' => $now,
            ]);
            $locked->forceFill([
                'voucher_claim_id' => $claim->getKey(),
                'status' => VoucherSliceExecutionStatus::Succeeded,
                'provider_confirmed_at' => $now,
                'settled_at' => $now,
                'version' => $locked->version + 1,
            ])->save();
            $this->appendEvent($locked, 'voucher.slice.consumed', 'succeeded', true);
        }, attempts: 5);

        $this->journal->deliverForExecution($execution->getKey());
    }

    public function indeterminate(VoucherSliceExecution $execution): void
    {
        DB::transaction(function () use ($execution): void {
            $locked = VoucherSliceExecution::query()->lockForUpdate()->findOrFail($execution->getKey());

            if ($locked->status === VoucherSliceExecutionStatus::Succeeded) {
                return;
            }

            $locked->forceFill([
                'status' => VoucherSliceExecutionStatus::Indeterminate,
                'indeterminate_at' => now(),
                'version' => $locked->version + 1,
            ])->save();
            $this->appendEvent($locked, 'voucher.slice.execution_indeterminate', 'indeterminate', true);
        }, attempts: 5);

        $this->journal->deliverForExecution($execution->getKey());
    }

    public function replayResult(VoucherSliceExecution $execution): SubmitPayCodeClaimResultData
    {
        $claim = $execution->claim;

        if (! $claim instanceof VoucherClaim || $execution->status !== VoucherSliceExecutionStatus::Succeeded) {
            throw new VoucherSliceExecutionConflict('This slice claim is already reserved or awaiting reconciliation.');
        }

        return new SubmitPayCodeClaimResultData(
            voucher_code: (string) $execution->voucher->code,
            claim_type: $claim->claim_type,
            claimed: true,
            status: $claim->status,
            requested_amount: $claim->requested_amount,
            disbursed_amount: $claim->disbursed_amount,
            currency: $claim->currency,
            remaining_balance: $claim->remaining_balance,
            fully_claimed: (bool) data_get($claim->meta, 'fully_claimed', false),
            disbursement: (array) data_get($claim->meta, 'disbursement', []),
            messages: (array) data_get($claim->meta, 'messages', []),
        );
    }

    public function plan(Voucher $voucher): ?VoucherSlicePlanData
    {
        $value = data_get($voucher->metadata, 'instructions.slice_plan');

        if ($value instanceof VoucherSlicePlanData) {
            return $value;
        }

        if (is_array($value)) {
            return VoucherSlicePlanData::from($value);
        }

        try {
            return $voucher->instructions?->slice_plan;
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array{0: array<int, array{id:string,label:string,sequence:int,amount_minor:int}>, 1:int} */
    private function selection(Voucher $voucher, VoucherSlicePlanData $plan, array $payload): array
    {
        $used = VoucherSliceExecutionItem::query()
            ->where('voucher_id', $voucher->getKey())
            ->pluck('slice_id')
            ->all();

        if ($plan->mode === VoucherSlicePlanMode::Flexible) {
            $amountMinor = $this->amountToMinor(data_get($payload, 'amount'), $plan->currency);
            $reserved = (int) VoucherSliceExecution::query()
                ->where('voucher_id', $voucher->getKey())
                ->whereIn('status', ['reserved', 'executing', 'succeeded', 'indeterminate'])
                ->sum('amount_minor');
            $count = VoucherSliceExecution::query()->where('voucher_id', $voucher->getKey())->count();

            if ($amountMinor < (int) $plan->min_amount_minor
                || $reserved + $amountMinor > $plan->total_minor
                || $count >= (int) $plan->max_slices) {
                throw ValidationException::withMessages(['amount' => 'The requested flexible slice is outside the remaining capacity.']);
            }

            return [[[
                'id' => 'draw_'.Str::lower((string) Str::ulid()),
                'label' => 'Flexible claim '.($count + 1),
                'sequence' => $count + 1,
                'amount_minor' => $amountMinor,
            ]], $amountMinor];
        }

        $available = $plan->slices->toCollection()
            ->reject(fn (VoucherSliceData $slice): bool => in_array($slice->id, $used, true))
            ->keyBy(fn (VoucherSliceData $slice): string => $slice->id);
        $selectedIds = $plan->mode === VoucherSlicePlanMode::Equal
            ? [$available->sortBy('sequence')->keys()->first()]
            : array_values(array_unique(array_filter(
                (array) data_get($payload, 'slice_ids', []),
                fn (mixed $id): bool => is_string($id) && $id !== '',
            )));

        if ($selectedIds === [] || $selectedIds === [null]) {
            throw ValidationException::withMessages(['slice_ids' => 'No claimable slice remains.']);
        }

        $selected = collect($selectedIds)->map(function (string $id) use ($available): array {
            $slice = $available->get($id);

            if (! $slice instanceof VoucherSliceData) {
                throw ValidationException::withMessages(['slice_ids' => "Slice [{$id}] is unavailable."]);
            }

            $now = now();

            if (($slice->claim_on !== null && $now->lt($slice->claim_on))
                || ($slice->claim_by !== null && $now->gt($slice->claim_by))) {
                throw ValidationException::withMessages(['slice_ids' => "Slice [{$id}] is outside its claim window."]);
            }

            return [
                'id' => $slice->id,
                'label' => $slice->label,
                'sequence' => $slice->sequence,
                'amount_minor' => $slice->amount_minor,
            ];
        })->values()->all();

        return [$selected, (int) collect($selected)->sum('amount_minor')];
    }

    /** @param array<int, array{id:string,label:string,sequence:int,amount_minor:int}> $selected */
    private function requestFingerprint(Voucher $voucher, VoucherSlicePlanData $plan, array $selected, int $amountMinor, array $payload): string
    {
        return hash('sha256', json_encode([
            'schema' => 'x-change.voucher-slice-request.v1',
            'voucher_id' => (string) $voucher->getKey(),
            'plan_fingerprint' => $plan->hash(),
            'slice_ids' => collect($selected)->pluck('id')->sort()->values()->all(),
            'requested_slice_ids' => collect((array) data_get($payload, 'slice_ids', []))
                ->filter(static fn (mixed $id): bool => is_string($id) && $id !== '')
                ->unique()
                ->sort()
                ->values()
                ->all(),
            'amount_minor' => $amountMinor,
            'requested_amount_minor' => $plan->mode === VoucherSlicePlanMode::Flexible
                ? $this->amountToMinor(data_get($payload, 'amount'), $plan->currency)
                : null,
            'currency' => $plan->currency,
                'claimant_hash' => hash_hmac(
                    'sha256',
                    (string) data_get($payload, 'mobile', ''),
                    (string) config('app.key'),
                ),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private function amountToMinor(mixed $amount, string $currency): int
    {
        if (! is_numeric($amount)) {
            return 0;
        }

        return Money::of((string) $amount, $currency)->getMinorAmount()->toInt();
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function enrichPayload(array $payload, VoucherSliceExecution $execution): array
    {
        $execution->loadMissing('items');
        data_set($payload, 'amount', $execution->amount_minor / 100);
        data_set($payload, 'slice_ids', $execution->items->pluck('slice_id')->all());
        data_set($payload, '_slice_execution.reference', $execution->reference);
        data_set($payload, '_slice_execution.provider_operation_reference', $execution->provider_operation_reference);
        data_set($payload, '_slice_execution.claim_number', $execution->claim_number);

        return $payload;
    }

    private function appendEvent(VoucherSliceExecution $execution, string $eventType, string $status, bool $providerCalls): void
    {
        $execution->loadMissing(['voucher', 'items']);
        $payload = [
            'voucher_reference' => (string) $execution->voucher_id,
            'execution_reference' => $execution->reference,
            'plan_fingerprint' => $execution->plan_fingerprint,
            'slice_ids' => $execution->items->pluck('slice_id')->all(),
            'claim_number' => $execution->claim_number,
            'status' => $status,
            'amount_minor' => $execution->amount_minor,
            'currency' => $execution->currency,
            'provider_calls' => $providerCalls,
        ];
        $fingerprint = hash('sha256', $execution->reference."\0".$eventType."\0".$status);

        VoucherSliceExecutionOutbox::query()->firstOrCreate(
            ['event_fingerprint' => $fingerprint],
            [
                'reference' => (string) Str::ulid(),
                'execution_id' => $execution->getKey(),
                'event_type' => $eventType,
                'payload' => $payload,
                'occurred_at' => now(),
            ],
        );
    }
}
