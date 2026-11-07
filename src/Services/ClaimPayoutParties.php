<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\EmiCore\Data\PayoutAddressData;
use LBHurtado\EmiCore\Data\PayoutPartyData;
use LBHurtado\EmiCore\Services\PayoutLocalityResolver;
use LBHurtado\Voucher\Models\Voucher;

final class ClaimPayoutParties
{
    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function resolve(Voucher $voucher, array $payload, string $recipientName): array
    {
        if (! config('x-change.payout_locality.enabled', false)) {
            return [];
        }
        $key = data_get($payload, '_meta.idempotency_key');
        $evidence = is_string($key) && $key !== '' ? app(ClaimPayoutLocalityContext::class)->find($voucher, $key) : null;
        $captured = data_get($evidence, 'captured');
        $resolver = app(PayoutLocalityResolver::class);

        return [
            'sender' => (new PayoutPartyData('sender', null, $resolver->resolve()))->toArray(),
            'recipient' => (new PayoutPartyData('recipient', $recipientName, $resolver->resolve(
                captured: is_array($captured) ? PayoutAddressData::from($captured) : null,
                ipCity: data_get($evidence, 'ip_locality.city'),
                ipRegion: data_get($evidence, 'ip_locality.region'),
            )))->toArray(),
        ];
    }
}
