<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Payment;

final class CampaignPaymentRuleKeys
{
    /** @var list<string> */
    public const array Supported = [
        'allowed_rails',
        'maximum_amount_minor',
        'maximum_payments',
        'minimum_amount_minor',
    ];

    /**
     * @param  array<string, mixed>  $rules
     * @return list<string>
     */
    public static function unsupported(array $rules): array
    {
        $keys = array_map(
            static fn (int|string $key): string => (string) $key,
            array_keys($rules),
        );

        return array_values(array_diff($keys, self::Supported));
    }
}
