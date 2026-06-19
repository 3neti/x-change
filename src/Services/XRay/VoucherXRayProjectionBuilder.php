<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\XRay;

use Illuminate\Support\Arr;

class VoucherXRayProjectionBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(mixed $voucher): array
    {
        $instructions = (array) data_get($voucher, 'instructions', []);
        $status = $this->xrayStatus((string) data_get($voucher, 'status', 'unknown'), $voucher);

        return [
            'status' => $status,
            'amount' => $this->formatAmount(
                data_get($voucher, 'amount'),
                (string) data_get($voucher, 'currency', 'PHP'),
            ),
            'issuer' => data_get($voucher, 'issuer_id'),
            'requirements' => $this->requirements($instructions),
            'remaining_slices' => $this->remainingSlices($instructions),
            'redirect_url' => data_get($instructions, 'rider.url'),
            'stages' => $this->stages($voucher, $instructions),
            'next_actions' => $this->nextActions($status, (string) data_get($voucher, 'code', '')),
            'allow' => [
                'amount' => false,
                'issuer' => false,
                'remaining_slices' => false,
                'rider_preclaim' => true,
                'redirect_url' => false,
            ],
        ];
    }

    protected function xrayStatus(string $status, mixed $voucher): string
    {
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
            ->filter(fn (mixed $field): bool => is_string($field) && $field !== '')
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
    protected function nextActions(string $status, string $code): array
    {
        if ($status !== 'claimable' && $status !== 'partially_claimable') {
            return [];
        }

        return [[
            'key' => 'claim',
            'label' => 'Start claim',
            'url' => '/x/claim?code='.rawurlencode($code),
        ]];
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
}
