<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support;

class VoucherInstructionPayloadFactory
{
    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public static function base(
        float $amount = 100.00,
        ?string $settlementRail = 'INSTAPAY',
        array $metadata = [],
    ): array {
        return [
            'cash' => [
                'amount' => $amount,
                'currency' => config('x-change.pricing.currency', 'PHP'),
                'settlement_rail' => $settlementRail,
                'validation' => [
                    'secret' => null,
                    'mobile' => null,
                    'payable' => null,
                    'country' => 'PH',
                    'location' => null,
                    'radius' => null,
                ],
                'fee_strategy' => 'absorb',
                'slice_mode' => null,
                'slices' => null,
                'max_slices' => null,
                'min_withdrawal' => null,
            ],
            'inputs' => [
                'fields' => [],
            ],
            'feedback' => [
                'email' => null,
                'mobile' => null,
                'webhook' => null,
            ],
            'rider' => [
                'message' => null,
                'url' => null,
                'redirect_timeout' => null,
                'splash' => null,
                'splash_timeout' => null,
                'og_source' => null,
            ],
            'count' => 1,
            'prefix' => 'TEST',
            'mask' => '****',
            'ttl' => null,
            'metadata' => $metadata,
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function make(
        float $amount = 100.00,
        ?string $settlementRail = 'INSTAPAY',
        array $metadata = [],
        array $overrides = [],
    ): array {
        return array_replace_recursive(
            static::base($amount, $settlementRail, $metadata),
            $overrides,
        );
    }
}
