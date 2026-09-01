<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\XRay;

use BackedEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract;
use LBHurtado\XChange\Services\Slices\VoucherSlicePlanProjection;
use LBHurtado\XChange\Services\VoucherCollectionProgressService;

class VoucherXRayProjectionBuilder
{
    public function __construct(
        private readonly VoucherSlicePlanProjection $slicePlans,
        private readonly VoucherFlowCapabilityResolverContract $capabilities,
        private readonly VoucherCollectionProgressService $progress,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(mixed $voucher, ?Voucher $sliceSource = null): array
    {
        $instructions = (array) data_get($voucher, 'instructions', []);
        $status = $this->xrayStatus((string) data_get($voucher, 'status', 'unknown'), $voucher);
        $sliceVoucher = $voucher instanceof Voucher ? $voucher : $sliceSource;
        $slicePlan = $sliceVoucher instanceof Voucher
            ? $this->slicePlans->forVoucher($sliceVoucher)
            : [];
        $flowCapabilities = $sliceVoucher instanceof Voucher
            ? $this->capabilities->resolve($sliceVoucher)
            : null;
        $collectionProgress = $sliceVoucher instanceof Voucher && $flowCapabilities?->can_collect === true
            ? $this->collectionProgress($sliceVoucher)
            : null;

        return [
            'status' => $status,
            'amount' => $this->formatAmount(
                data_get($voucher, 'amount'),
                (string) data_get($voucher, 'currency', 'PHP'),
            ),
            'issuer' => data_get($voucher, 'issuer_id'),
            'requirements' => $this->requirements($instructions),
            'collection_progress' => $collectionProgress,
            'slice_plan' => $slicePlan,
            'remaining_slices' => $sliceVoucher instanceof Voucher
                ? data_get($slicePlan, 'rows', [])
                : $this->remainingSlices($instructions),
            'redirect_url' => data_get($instructions, 'rider.url'),
            'stages' => $this->stages($voucher, $instructions),
            'next_actions' => $this->nextActions($status, (string) data_get($voucher, 'code', ''), $sliceVoucher),
            'allow' => [
                'amount' => false,
                'issuer' => false,
                'remaining_slices' => $slicePlan !== [],
                'rider_preclaim' => true,
                'redirect_url' => false,
            ],
        ];
    }

    protected function xrayStatus(string $status, mixed $voucher): string
    {
        if ($voucher instanceof Voucher && $this->capabilities->canCollect($voucher)) {
            $progress = $this->progress->compute($voucher);

            if ($progress->is_fully_collected) {
                return 'paid';
            }

            return 'payable';
        }

        if ($status === 'redeemed' || (bool) data_get($voucher, 'fully_claimed', false) === true) {
            return 'redeemed';
        }

        if ($status === 'expired') {
            return 'expired';
        }

        if ($status === 'cancelled') {
            return 'hidden';
        }

        if ((bool) data_get($voucher, 'claimed', false) === true) {
            return 'partially_claimable';
        }

        return 'claimable';
    }

    protected function formatAmount(mixed $amount, string $currency): ?string
    {
        if (! is_numeric($amount)) {
            return null;
        }

        return new \NumberFormatter('en_PH', \NumberFormatter::CURRENCY)
            ->formatCurrency((float) $amount, $currency) ?: null;
    }

    /**
     * @param  array<string, mixed>  $instructions
     * @return array<int, array<string, mixed>>
     */
    protected function requirements(array $instructions): array
    {
        $fields = Arr::wrap(data_get($instructions, 'inputs.fields', []));
        $requirements = collect($fields)
            ->map(fn (mixed $field): ?string => $this->fieldKey($field))
            ->filter(fn (?string $field): bool => is_string($field) && $field !== '')
            ->map(fn (string $field): array => [
                'key' => $field,
                'label' => $this->label($field),
                'required' => true,
                'description' => $this->description($field),
            ])
            ->values()
            ->all();

        $validation = (array) data_get($instructions, 'cash.validation', []);

        if (filled(data_get($validation, 'secret'))) {
            $requirements[] = [
                'key' => 'secret',
                'label' => 'Secret / PIN',
                'required' => true,
                'description' => 'A matching issuer-provided secret is required.',
            ];
        }

        if (filled(data_get($validation, 'mobile'))) {
            $requirements[] = [
                'key' => 'assigned_mobile',
                'label' => 'Assigned mobile number',
                'required' => true,
                'description' => 'Only the assigned mobile number can claim this Pay Code.',
            ];
        }

        return $requirements;
    }

    /**
     * @param  array<string, mixed>  $instructions
     * @return array<int, array<string, mixed>>
     */
    protected function remainingSlices(array $instructions): array
    {
        $slices = Arr::wrap(data_get($instructions, 'metadata.slices', data_get($instructions, 'cash.slices', [])));

        return collect($slices)
            ->filter(fn (mixed $slice): bool => is_array($slice))
            ->map(fn (array $slice, int $index): array => [
                'id' => (string) ($slice['id'] ?? 'slice_'.($index + 1)),
                'label' => (string) ($slice['description'] ?? 'Slice '.($index + 1)),
                'amount' => $this->formatAmount($slice['amount'] ?? null, (string) data_get($instructions, 'cash.currency', 'PHP')),
                'claim_on' => $slice['claim_on'] ?? null,
                'claim_by' => $slice['claim_by'] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $instructions
     * @return array<int, array<string, mixed>>
     */
    protected function stages(mixed $voucher, array $instructions): array
    {
        $stages = data_get($voucher, 'rider.stages.stages');

        if (is_array($stages) && $stages !== []) {
            return $stages;
        }

        $message = data_get($instructions, 'rider.message');

        if (! is_string($message) || trim($message) === '') {
            return [];
        }

        return [[
            'type' => 'message',
            'payload' => [
                'message' => $message,
            ],
        ]];
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function nextActions(string $status, string $code, ?Voucher $voucher = null): array
    {
        if (
            $voucher instanceof Voucher
            && $this->capabilities->canCollect($voucher)
            && ! $this->progress->compute($voucher)->is_fully_collected
        ) {
            return [[
                'key' => 'pay',
                'label' => 'Pay now',
                'url' => Route::has('x-change.pay.show')
                    ? route('x-change.pay.show', ['code' => $code], false)
                    : '/x/pay/'.rawurlencode($code),
            ]];
        }

        if ($status !== 'claimable' && $status !== 'partially_claimable') {
            return [];
        }

        return [[
            'key' => 'claim',
            'label' => 'Start claim',
            'url' => '/x/claim?code='.rawurlencode($code),
        ]];
    }

    protected function fieldKey(mixed $field): ?string
    {
        if ($field instanceof BackedEnum) {
            return is_string($field->value) ? $field->value : null;
        }

        if (is_string($field)) {
            return trim($field);
        }

        return null;
    }

    protected function label(string $field): string
    {
        return str($field)->replace('_', ' ')->title()->toString();
    }

    protected function description(string $field): ?string
    {
        return match ($field) {
            'mobile' => 'Mobile number is required for claim verification.',
            'bank_account', 'bank_code', 'account_number' => 'Bank account details are required for payout.',
            'kyc' => 'Identity verification is required before claim completion.',
            'otp' => 'One-time password verification is required.',
            'location' => 'Location capture is required by the issuer.',
            'selfie' => 'Selfie capture is required by the issuer.',
            'signature' => 'Signature capture is required by the issuer.',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function collectionProgress(Voucher $voucher): array
    {
        $progress = $this->progress->compute($voucher);

        return [
            'currency' => $progress->currency,
            'target_amount_minor' => $progress->target_amount_minor,
            'collected_total_minor' => $progress->collected_total_minor,
            'remaining_to_collect_minor' => $progress->remaining_to_collect_minor,
            'overpaid_amount_minor' => $progress->overpaid_amount_minor,
            'is_fully_collected' => $progress->is_fully_collected,
            'is_overpaid' => $progress->is_overpaid,
            'target_amount' => $progress->targetAmount(),
            'collected_total' => $progress->collectedTotal(),
            'remaining' => $progress->remaining(),
        ];
    }
}
