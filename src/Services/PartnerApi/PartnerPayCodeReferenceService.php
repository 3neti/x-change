<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\PartnerApi;

use Brick\Money\Money;
use LBHurtado\Voucher\Models\Voucher;

class PartnerPayCodeReferenceService
{
    /** @param array<string, mixed> $payload */
    public function termsHash(array $payload): string
    {
        $currency = strtoupper((string) data_get($payload, 'cash.currency'));
        $voucherType = strtolower(trim((string) data_get($payload, 'voucher_type')));
        $voucherType = $voucherType !== '' ? $voucherType : 'redeemable';
        $amount = in_array($voucherType, ['payable', 'settlement'], true)
            ? (data_get($payload, 'target_amount') ?? data_get($payload, 'cash.amount'))
            : data_get($payload, 'cash.amount');

        $terms = [
            'amount_minor' => Money::of((string) $amount, $currency)->getMinorAmount()->toInt(),
            'currency' => $currency,
            'voucher_type' => $voucherType,
        ];

        return hash('sha256', json_encode($terms, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    public function externalReference(Voucher $voucher): ?string
    {
        $reference = data_get(
            $voucher->metadata,
            'instructions.metadata.custom.external_reference',
        );

        return is_string($reference) && $reference !== '' ? $reference : null;
    }
}
