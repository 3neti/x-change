<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\PublicIssuance;

final readonly class PublicIssuancePayloadFactory
{
    /**
     * @return array<string, mixed>
     */
    public function make(int $amountMinor, string $currency): array
    {
        return [
            'cash' => [
                'amount' => $amountMinor / 100,
                'currency' => strtoupper($currency),
                'settlement_rail' => 'INSTAPAY',
                'validation' => [
                    'secret' => null,
                    'mobile' => null,
                    'payable' => null,
                    'country' => 'PH',
                    'location' => null,
                    'radius' => null,
                ],
            ],
            'inputs' => ['fields' => []],
            'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
            'rider' => [
                'message' => null,
                'url' => null,
                'redirect_timeout' => null,
                'splash' => null,
                'splash_timeout' => null,
                'og_source' => null,
            ],
            'count' => 1,
            'prefix' => 'PAY',
            'mask' => '****',
            'ttl' => null,
            'voucher_type' => 'redeemable',
            'metadata' => [
                'source' => 'public_ai_discovery',
                'flow_type' => 'redeemable',
            ],
        ];
    }
}
