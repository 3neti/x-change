<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\VoucherClaim;

class NamedVoucherSliceService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalizeIssuancePayload(array $payload): array
    {
        $slices = data_get($payload, 'metadata.slices');

        if (! is_array($slices) || $slices === []) {
            return $payload;
        }

        $normalized = $this->normalizeSlices($slices);
        $totalMinor = $this->amountToMinor(data_get($payload, 'cash.amount'));
        $sliceTotalMinor = array_sum(array_column($normalized, 'amount_minor'));

        if ($totalMinor <= 0 || $sliceTotalMinor !== $totalMinor) {
            throw ValidationException::withMessages([
                'metadata.slices' => 'Named slice amounts must equal the Pay Code amount.',
            ]);
        }

        data_forget($payload, 'metadata.slices');
        data_forget($payload, 'metadata.slice_policy');
        data_set($payload, 'metadata.custom.named_slices', $this->withoutInternalAmounts($normalized));
        data_set($payload, 'metadata.custom.named_slice_policy', [
            'mode' => 'named',
            'selection' => 'one_or_many',
            'enforced' => true,
        ]);
        data_set($payload, 'cash.slice_mode', 'open');
        data_set($payload, 'cash.max_slices', count($normalized));
        data_set($payload, 'cash.min_withdrawal', min(array_column($normalized, 'amount')));
        data_forget($payload, 'cash.slices');

        return $payload;
    }

    public function hasNamedSlices(Voucher $voucher): bool
    {
        return $this->slices($voucher) !== [];
    }

    public function hasUnclaimedSlices(Voucher $voucher): bool
    {
        if (! $this->hasNamedSlices($voucher)) {
            return false;
        }

        return collect($this->claimOptions($voucher))
            ->contains(fn (array $slice): bool => ($slice['claimed'] ?? false) !== true);
    }

    public function allSlicesClaimed(Voucher $voucher): bool
    {
        return $this->hasNamedSlices($voucher)
            && ! $this->hasUnclaimedSlices($voucher);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function claimOptions(Voucher $voucher): array
    {
        $claimedIds = $this->claimedSliceIds($voucher);

        return collect($this->slices($voucher))
            ->map(function (array $slice) use ($claimedIds): array {
                $availability = $this->availability($slice);
                $claimed = in_array($slice['id'], $claimedIds, true);

                return [
                    ...$slice,
                    'claimed' => $claimed,
                    'available' => ! $claimed && $availability['available'],
                    'disabled' => $claimed || ! $availability['available'],
                    'disabled_reason' => $claimed ? 'Already claimed.' : $availability['reason'],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function enrichClaimPayload(Voucher $voucher, array $payload): array
    {
        if (! $this->hasNamedSlices($voucher)) {
            return $payload;
        }

        $selectedIds = $this->selectedSliceIds($payload);

        if ($selectedIds === []) {
            throw ValidationException::withMessages([
                'slice_ids' => 'Select at least one slice to claim.',
            ]);
        }

        $options = collect($this->claimOptions($voucher))->keyBy('id');
        $selected = [];

        foreach ($selectedIds as $id) {
            $slice = $options->get($id);

            if (! is_array($slice)) {
                throw ValidationException::withMessages([
                    'slice_ids' => "Selected slice [{$id}] does not exist.",
                ]);
            }

            if (($slice['available'] ?? false) !== true) {
                throw ValidationException::withMessages([
                    'slice_ids' => (string) ($slice['disabled_reason'] ?? "Selected slice [{$id}] is not claimable."),
                ]);
            }

            $selected[] = $slice;
        }

        $amount = array_sum(array_map(
            static fn (array $slice): float => (float) $slice['amount'],
            $selected
        ));

        data_set($payload, 'amount', $amount);
        data_set($payload, 'slice_ids', $selectedIds);
        data_set($payload, '_named_slices.selected', $selected);

        return $payload;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function slices(Voucher $voucher): array
    {
        $slices = data_get($voucher->metadata ?? [], 'instructions.metadata.custom.named_slices')
            ?? data_get($voucher->metadata ?? [], 'instructions.metadata.slices');

        if (! is_array($slices)) {
            return [];
        }

        return $this->withoutInternalAmounts($this->normalizeSlices($slices));
    }

    /**
     * @param  array<int, mixed>  $slices
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeSlices(array $slices): array
    {
        $normalized = collect($slices)
            ->values()
            ->map(function (mixed $slice, int $index) use ($slices): array {
                if (! is_array($slice)) {
                    throw ValidationException::withMessages([
                        "metadata.slices.{$index}" => 'Each named slice must be an object.',
                    ]);
                }

                $amountMinor = $this->amountToMinor($slice['amount'] ?? null);

                if ($amountMinor <= 0) {
                    throw ValidationException::withMessages([
                        "metadata.slices.{$index}.amount" => 'Each named slice amount must be greater than zero.',
                    ]);
                }

                $id = $this->normalizeSliceId($slice['id'] ?? null, $index);
                $description = trim((string) ($slice['description'] ?? ''));

                if ($description === '') {
                    $description = count($slices) === 1 ? 'Whole amount' : 'Slice '.($index + 1);
                }

                $claimOn = $this->normalizeDate($slice['claim_on'] ?? null);
                $claimBy = $this->normalizeDate($slice['claim_by'] ?? null);

                if ($claimOn !== null && $claimBy !== null && Carbon::parse($claimBy)->lt(Carbon::parse($claimOn))) {
                    throw ValidationException::withMessages([
                        "metadata.slices.{$index}.claim_by" => 'Claim by must be after claim on.',
                    ]);
                }

                return [
                    'id' => $id,
                    'amount' => $amountMinor / 100,
                    'amount_minor' => $amountMinor,
                    'description' => $description,
                    'tag' => $this->nullableString($slice['tag'] ?? null),
                    'claim_on' => $claimOn,
                    'claim_by' => $claimBy,
                    'metadata' => is_array($slice['metadata'] ?? null) ? $slice['metadata'] : [],
                ];
            })
            ->all();

        $ids = array_column($normalized, 'id');

        if (count($ids) !== count(array_unique($ids))) {
            throw ValidationException::withMessages([
                'metadata.slices' => 'Named slice IDs must be unique.',
            ]);
        }

        return $normalized;
    }

    /**
     * @param  array<int, array<string, mixed>>  $slices
     * @return array<int, array<string, mixed>>
     */
    protected function withoutInternalAmounts(array $slices): array
    {
        return array_map(static function (array $slice): array {
            unset($slice['amount_minor']);

            return $slice;
        }, $slices);
    }

    /**
     * @return array<int, string>
     */
    protected function claimedSliceIds(Voucher $voucher): array
    {
        return VoucherClaim::query()
            ->where('voucher_id', $voucher->getKey())
            ->whereNotIn('status', ['failed'])
            ->get()
            ->flatMap(fn (VoucherClaim $claim) => data_get($claim->meta ?? [], 'named_slices.selected_ids', []))
            ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $slice
     * @return array{available: bool, reason: string|null}
     */
    protected function availability(array $slice): array
    {
        $now = now();
        $claimOn = $slice['claim_on'] ?? null;
        $claimBy = $slice['claim_by'] ?? null;

        if (is_string($claimOn) && $claimOn !== '' && $now->lt(Carbon::parse($claimOn))) {
            return [
                'available' => false,
                'reason' => 'Available on '.Carbon::parse($claimOn)->toDayDateTimeString().'.',
            ];
        }

        if (is_string($claimBy) && $claimBy !== '' && $now->gt(Carbon::parse($claimBy))) {
            return [
                'available' => false,
                'reason' => 'Claim window has ended.',
            ];
        }

        return [
            'available' => true,
            'reason' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    protected function selectedSliceIds(array $payload): array
    {
        $selected = data_get($payload, 'slice_ids', data_get($payload, 'inputs.slice_ids', []));

        if (is_string($selected)) {
            $selected = array_filter(array_map('trim', explode(',', $selected)));
        }

        if (! is_array($selected)) {
            return [];
        }

        return collect($selected)
            ->map(fn (mixed $id): string => trim((string) $id))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function normalizeSliceId(mixed $value, int $index): string
    {
        $id = is_string($value) ? trim($value) : '';

        if ($id === '') {
            return 'slice_'.($index + 1);
        }

        return Str::of($id)
            ->lower()
            ->replaceMatches('/[^a-z0-9_-]+/', '_')
            ->trim('_')
            ->whenEmpty(fn () => Str::of('slice_'.($index + 1)))
            ->toString();
    }

    protected function normalizeDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value)->toIso8601String();
    }

    protected function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    protected function amountToMinor(mixed $amount): int
    {
        if (! is_numeric($amount)) {
            return 0;
        }

        return (int) round(((float) $amount) * 100);
    }
}
